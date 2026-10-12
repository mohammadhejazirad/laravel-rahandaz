<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Audit\Enums\AuditResult;
use App\Infrastructure\Secrets\CredentialStore;
use App\Infrastructure\Secrets\EnvelopeCipher;
use App\Infrastructure\Secrets\Exceptions\DecryptionFailed;
use App\Models\Credential;
use App\Models\CredentialAccessEvent;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\LocalHost;
use App\Models\Membership;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssertsPostgresConstraints;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class DatabaseSecurityTest extends TestCase
{
    use AssertsPostgresConstraints, CreatesPanelAccount, RefreshDatabase;

    public function test_credentials_stay_hidden_and_decryption_is_audited(): void
    {
        [$owner, $workspace] = $this->panelAccount();
        $secret = 'ghp_test_secret_value';
        $store = app(CredentialStore::class);
        $credential = $store->store($workspace, $owner, 'github', $secret, 'github');

        $this->assertSame(EnvelopeCipher::ALGORITHM, $credential->algorithm);
        $this->assertArrayNotHasKey('envelope', $credential->toArray());
        $this->assertNotContains('revoked_at', $credential->getFillable());
        $this->assertNotContains('last_login_at', (new User)->getFillable());

        $credential->update(['revoked_at' => now()]);
        $this->assertNull($credential->fresh()->revoked_at);

        $viewer = User::factory()->create();
        Membership::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $viewer->id,
            'role' => MembershipRole::Viewer,
        ]);

        try {
            $store->openFor($credential, $viewer);
            $this->fail('A viewer decrypted a credential.');
        } catch (AuthorizationException) {
            $this->assertSame(AuditResult::Denied, $credential->accessEvents()->where('actor_id', $viewer->id)->firstOrFail()->result);
        }

        $this->assertSame($secret, $store->openFor($credential->fresh(), $owner));
        $this->assertSame(AuditResult::Success, $credential->accessEvents()->where('actor_id', $owner->id)->firstOrFail()->result);
        $encoded = json_encode(DB::table('credential_access_events')->get(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($secret, $encoded);

        $envelope = $credential->fresh()->envelope;
        $envelope['ciphertext'] = base64_encode(random_bytes(32));
        $credential->fresh()->forceFill(['envelope' => $envelope])->save();

        try {
            $store->openFor($credential->fresh(), $owner);
            $this->fail('A tampered envelope decrypted.');
        } catch (DecryptionFailed) {
            $this->assertTrue(
                CredentialAccessEvent::query()->where('result', AuditResult::Failure)->exists(),
            );
        }
    }

    public function test_login_records_activity_and_secrets_cannot_cross_workspaces(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $owner->update(['last_login_at' => now(), 'last_activity_at' => now()]);
        $this->assertNull($owner->fresh()->last_login_at);

        $this->post(route('login.store'), [
            'identifier' => $owner->username,
            'password' => 'Password-test-123',
        ])->assertRedirect(route('dashboard'));

        $this->assertNotNull($owner->fresh()->last_login_at);
        $this->assertNotNull($owner->fresh()->last_activity_at);

        [$other, $otherWorkspace] = $this->panelAccount();
        $credential = app(CredentialStore::class)->store($workspace, $owner, 'github', 'ghp_other_scope');
        $host = LocalHost::factory()->create(['workspace_id' => $otherWorkspace->id, 'slug' => 'local-host']);
        $project = Project::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'local_host_id' => $host->id,
        ]);
        $environment = Environment::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $otherWorkspace->id,
            'local_host_id' => $host->id,
            'slug' => 'production',
            'release_path' => '/srv/rahandaz/projects/'.$project->slug.'/production/releases',
            'shared_path' => '/srv/rahandaz/projects/'.$project->slug.'/production/shared',
        ]);

        $this->assertSqlstate('23503', fn () => EnvironmentVariable::query()->create([
            'environment_id' => $environment->id,
            'workspace_id' => $otherWorkspace->id,
            'key' => 'GIT_TOKEN',
            'is_secret' => true,
            'credential_id' => $credential->id,
        ]));

        try {
            app(CredentialStore::class)->openFor($credential, $other);
            $this->fail('Another workspace decrypted a credential.');
        } catch (AuthorizationException) {
            $this->assertSame(0, Credential::query()->where('workspace_id', $otherWorkspace->id)->count());
        }
    }
}
