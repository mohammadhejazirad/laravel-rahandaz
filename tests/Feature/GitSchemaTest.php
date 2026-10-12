<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Git\Enums\DeployKeyState;
use App\Domain\Git\Enums\GitAuthMethod;
use App\Domain\Git\Enums\GitProvider;
use App\Domain\Git\Enums\WebhookProcessingState;
use App\Domain\Git\GitRemoteUrl;
use App\Domain\Git\WebhookDeliveryRecorder;
use App\Domain\Git\WebhookSignature;
use App\Infrastructure\Secrets\CredentialStore;
use App\Models\DeployKey;
use App\Models\Deployment;
use App\Models\GitConnection;
use App\Models\GitWebhook;
use App\Models\GitWebhookDelivery;
use App\Models\LocalHost;
use App\Models\Project;
use App\Models\RemoteBranch;
use App\Models\RemoteRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssertsPostgresConstraints;
use Tests\Concerns\CreatesPanelAccount;
use Tests\Support\OpenSshFixture;
use Tests\TestCase;

class GitSchemaTest extends TestCase
{
    use AssertsPostgresConstraints, CreatesPanelAccount, RefreshDatabase;

    public function test_connections_repositories_and_public_urls_stay_scoped(): void
    {
        [$user, $workspace] = $this->panelAccount();
        $github = GitConnection::factory()->create(['workspace_id' => $workspace->id]);
        $gitlab = GitConnection::factory()->create([
            'workspace_id' => $workspace->id,
            'provider' => GitProvider::Gitlab,
            'auth_method' => GitAuthMethod::PersonalAccessToken,
            'base_url' => 'https://gitlab.com',
            'external_account_id' => 'group'.bin2hex(random_bytes(2)),
            'external_account_type' => 'group',
        ]);
        GitConnection::factory()->create([
            'workspace_id' => $workspace->id,
            'provider' => GitProvider::Gitlab,
            'auth_method' => GitAuthMethod::GitlabToken,
            'base_url' => 'https://gitlab.example.com',
            'external_account_id' => 'self'.bin2hex(random_bytes(2)),
        ]);

        $this->assertCount(3, $workspace->gitConnections()->get());
        $this->assertNotSame($github->credential_id, $gitlab->credential_id);

        $urls = app(GitRemoteUrl::class);
        $this->assertTrue($urls->acceptsPublicHttps('https://gitlab.example.com'));
        $this->assertTrue($urls->acceptsPublicHttps('https://gitlab.com/group/app'));
        $this->assertFalse($urls->acceptsPublicHttps('https://user:token@gitlab.example.com/group/app.git'));
        $this->assertFalse($urls->acceptsPublicHttps('https://192.168.1.1'));
        $this->assertFalse($urls->acceptsPublicHttps('https://169.254.169.254/latest'));
        $this->assertFalse($urls->acceptsPublicHttps('http://gitlab.example.com'));

        $this->assertSqlstate('23514', fn () => GitConnection::factory()->create([
            'workspace_id' => $workspace->id,
            'provider' => GitProvider::Gitlab,
            'auth_method' => GitAuthMethod::PersonalAccessToken,
            'base_url' => 'https://10.1.2.3',
            'external_account_id' => 'blocked1',
        ]));

        $repository = RemoteRepository::factory()->create([
            'workspace_id' => $workspace->id,
            'git_connection_id' => $github->id,
        ]);
        $branch = RemoteBranch::factory()->create([
            'remote_repository_id' => $repository->id,
            'name' => 'main',
            'head_sha' => str_repeat('ab', 20),
        ]);
        $this->assertTrue($repository->branches()->whereKey($branch->id)->exists());

        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id]);
        $first = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'local_host_id' => $host->id,
            'remote_repository_id' => $repository->id,
            'created_by' => $user->id,
        ]);
        $second = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'local_host_id' => $host->id,
            'remote_repository_id' => $repository->id,
            'created_by' => $user->id,
        ]);
        $this->assertCount(2, $repository->projects()->get());
        $this->assertNotSame($first->slug, $second->slug);

        $this->assertSqlstate('23505', fn () => RemoteRepository::factory()->create([
            'workspace_id' => $workspace->id,
            'git_connection_id' => $github->id,
            'provider_repository_id' => $repository->provider_repository_id,
            'full_name' => 'acme/duplicate',
            'clone_url' => 'https://github.com/acme/duplicate.git',
        ]));

        $credential = app(CredentialStore::class)->store($workspace, $user, 'deploy_key', 'not-a-column');
        $this->assertSqlstate('23514', fn () => DeployKey::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $first->id,
            'public_key' => OpenSshFixture::PUBLIC_KEY,
            'fingerprint' => OpenSshFixture::FINGERPRINT,
            'credential_id' => $credential->id,
            'read_only' => false,
            'provider_state' => DeployKeyState::Pending,
        ]));
        DeployKey::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $first->id,
            'public_key' => OpenSshFixture::PUBLIC_KEY,
            'fingerprint' => OpenSshFixture::FINGERPRINT,
            'credential_id' => $credential->id,
            'read_only' => true,
            'provider_state' => DeployKeyState::Pending,
        ]);
    }

    public function test_webhook_deliveries_store_a_digest_and_ignore_duplicates(): void
    {
        [$user, $workspace] = $this->panelAccount();
        $connection = GitConnection::factory()->create(['workspace_id' => $workspace->id]);
        $repository = RemoteRepository::factory()->create([
            'workspace_id' => $workspace->id,
            'git_connection_id' => $connection->id,
        ]);
        $secret = 'whsec-'.bin2hex(random_bytes(16));
        $credential = app(CredentialStore::class)->store($workspace, $user, 'webhook', $secret);
        $webhook = GitWebhook::query()->create([
            'workspace_id' => $workspace->id,
            'git_connection_id' => $connection->id,
            'remote_repository_id' => $repository->id,
            'provider' => GitProvider::Github,
            'external_id' => 'hook'.bin2hex(random_bytes(3)),
            'credential_id' => $credential->id,
            'enabled' => true,
            'subscribed_events' => ['push'],
        ]);

        $payload = '{"ref":"refs/heads/main","token":"should-not-be-stored"}';
        $header = 'sha256='.hash_hmac('sha256', $payload, $secret);
        $signatures = app(WebhookSignature::class);
        $recorder = app(WebhookDeliveryRecorder::class);
        $valid = $recorder->record($webhook, 'delivery-1', 'push', $payload, $signatures->github($payload, $secret, $header));
        $duplicate = $recorder->record($webhook, 'delivery-1', 'push', $payload, true);
        $rejected = $recorder->record($webhook, 'delivery-2', 'push', $payload, $signatures->github($payload, $secret, 'sha256=nope'));

        $this->assertSame($valid->id, $duplicate->id);
        $this->assertSame(hash('sha256', $payload), $valid->payload_digest);
        $this->assertSame(WebhookProcessingState::Rejected, $rejected->processing_state);
        $this->assertSame('SIGNATURE_INVALID', $rejected->failure_code);
        $this->assertSame(2, GitWebhookDelivery::query()->count());
        $this->assertSame(0, Deployment::query()->count());

        $stored = json_encode(DB::table('git_webhook_deliveries')->get(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('should-not-be-stored', $stored);
        $this->assertStringNotContainsString($secret, $stored);
    }
}
