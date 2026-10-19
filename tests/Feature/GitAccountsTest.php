<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Audit\AuditRecorder;
use App\Domain\Git\Enums\GitConnectionState;
use App\Domain\Git\Enums\GitProvider;
use App\Domain\Git\Exceptions\GitRejected;
use App\Domain\Git\GitConnector;
use App\Domain\Git\GitOAuthClient;
use App\Domain\Projects\DeploymentWorkflow;
use App\Domain\Projects\Exceptions\DeploymentRejected;
use App\Infrastructure\Http\HostAddressResolver;
use App\Infrastructure\Secrets\CredentialStore;
use App\Jobs\SyncGitRepositories;
use App\Models\AuditEvent;
use App\Models\Environment;
use App\Models\GitConnection;
use App\Models\GitProviderSetting;
use App\Models\Membership;
use App\Models\Project;
use App\Models\RemoteRepository;
use App\Models\ResourceGrant;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class GitAccountsTest extends TestCase
{
    use CreatesPanelAccount, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.ssr.enabled' => false]);
        Http::preventStrayRequests();
        $this->app->instance(HostAddressResolver::class, new class implements HostAddressResolver
        {
            public function addresses(string $host): array
            {
                return ['1.1.1.1'];
            }
        });
        Cache::flush();
    }

    public function test_manifest_registration_stores_only_encrypted_client_secret_and_continues_to_installation(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $this->actingAs($owner)->post(route('git.manifest.start'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('git/register')->where('manifest', function ($json) {
                $manifest = json_decode($json, true);
                $this->assertSame(['contents' => 'read', 'metadata' => 'read'], $manifest['default_permissions']);
                $this->assertArrayNotHasKey('hook_attributes', $manifest);
                $this->assertSame(route('git.manifest.callback'), $manifest['redirect_url']);
                $this->assertSame([route('git.callback', 'github')], $manifest['callback_urls']);

                return true;
            })->missing('client_secret'));
        $state = session('git_manifest.state');
        Http::fake(['api.github.com/app-manifests/fixture-code/conversions' => Http::response([
            'client_id' => 'manifest-client', 'client_secret' => 'manifest-secret-fixture', 'slug' => 'manifest-app',
            'pem' => 'unused-private-key-fixture', 'webhook_secret' => 'unused-webhook-fixture',
        ])]);
        $this->get(route('git.manifest.callback', ['state' => $state, 'code' => 'fixture-code']))
            ->assertRedirect('https://github.com/apps/manifest-app/installations/new?state='.session('git_oauth.github.state'));
        $setting = GitProviderSetting::query()->firstOrFail();
        $this->assertSame($workspace->id, $setting->workspace_id);
        $this->assertSame('manifest-secret-fixture', app(CredentialStore::class)->openFor($setting->credential, $owner));
        $this->assertStringNotContainsString('manifest-secret-fixture', $setting->credential->toJson());
        $this->assertStringNotContainsString('unused-private-key-fixture', $setting->credential->toJson());
        $this->assertStringNotContainsString('manifest-secret-fixture', AuditEvent::query()->get()->toJson());
        $this->get(route('git.index'))->assertOk()->assertDontSee('manifest-secret-fixture')->assertDontSee('unused-private-key-fixture');
        $this->get(route('git.manifest.callback', ['state' => $state, 'code' => 'fixture-code']))->assertSessionHas('error');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/app-manifests/fixture-code/conversions') && $request->body() === '{}');
        $this->assertDatabaseCount('git_provider_settings', 1);
        $this->assertDatabaseCount('git_connections', 0);
    }

    public function test_manifest_rejects_invalid_expired_or_cross_workspace_state_without_http(): void
    {
        [$owner] = $this->panelAccount(setupComplete: true);
        $this->actingAs($owner)->post(route('git.manifest.start'))->assertOk();
        $this->get(route('git.manifest.callback', ['state' => 'wrong', 'code' => 'fixture']))->assertSessionHas('error');
        $this->post(route('git.manifest.start'))->assertOk();
        $state = session('git_manifest.state');
        $this->withSession(['git_manifest.expires' => time() - 1])->get(route('git.manifest.callback', ['state' => $state, 'code' => 'fixture']))->assertSessionHas('error');
        $this->post(route('git.manifest.start'))->assertOk();
        $state = session('git_manifest.state');
        $this->withSession(['git_manifest.workspace' => 'different-workspace'])->get(route('git.manifest.callback', ['state' => $state, 'code' => 'fixture']))->assertSessionHas('error');
        Http::assertNothingSent();
        $this->assertDatabaseCount('git_provider_settings', 0);
    }

    public function test_manifest_failure_is_sanitized_and_existing_setting_is_not_replaced(): void
    {
        [$owner] = $this->panelAccount(setupComplete: true);
        $this->actingAs($owner)->post(route('git.manifest.start'))->assertOk();
        $state = session('git_manifest.state');
        Http::fake(['api.github.com/app-manifests/*' => Http::response(['client_secret' => 'response-secret-fixture'], 500)]);
        $this->get(route('git.manifest.callback', ['state' => $state, 'code' => 'fixture']))->assertSessionHas('error');
        $this->assertStringNotContainsString('response-secret-fixture', json_encode(session()->all()));
        $this->assertDatabaseCount('git_provider_settings', 0);
        $this->configureProvider($owner, 'github');
        $this->post(route('git.manifest.start'))->assertRedirect();
        $this->assertSame('test-client', GitProviderSetting::query()->firstOrFail()->client_id);
        Http::assertSentCount(1);
    }

    public function test_manifest_registration_requires_permission_to_manage_secrets(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        Membership::query()->where('workspace_id', $workspace->id)->where('user_id', $owner->id)->update(['role' => MembershipRole::Viewer]);
        $this->actingAs($owner)->post(route('git.manifest.start'))->assertForbidden();
        $this->get(route('git.manifest.callback', ['state' => 'fixture', 'code' => 'fixture']))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_repository_search_is_server_side_literal_and_connection_scoped(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $connection = GitConnection::factory()->create(['workspace_id' => $workspace->id]);
        RemoteRepository::factory()->create(['workspace_id' => $workspace->id, 'git_connection_id' => $connection->id, 'full_name' => 'team/Match_repo']);
        RemoteRepository::factory()->create(['workspace_id' => $workspace->id, 'git_connection_id' => $connection->id, 'full_name' => 'team/other']);
        $this->actingAs($owner)->get(route('git.show', ['connection' => $connection, 'search' => 'match_']))->assertInertia(fn (Assert $page) => $page
            ->has('repositories.data', 1)->where('repositories.data.0.full_name', 'team/Match_repo')->where('filters.search', 'match_'));
        $this->get(route('git.show', ['connection' => $connection, 'search' => '%']))->assertInertia(fn (Assert $page) => $page->has('repositories.data', 0));
    }

    private function configureProvider($owner, string $provider): void
    {
        $this->actingAs($owner)->put(route('git.settings', $provider), [
            'client_id' => 'test-client', 'client_secret' => 'fixture-client-secret', 'app_slug' => $provider === 'github' ? 'fixture-app' : null,
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_settings_are_encrypted_and_never_sent_back_and_cannot_be_written_by_viewers(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $this->configureProvider($owner, 'github');
        $setting = GitProviderSetting::query()->firstOrFail();
        $this->assertStringNotContainsString('fixture-client-secret', json_encode($setting->credential->envelope));
        $this->assertSame('fixture-client-secret', app(CredentialStore::class)->openFor($setting->credential, $owner));
        $this->get(route('git.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('git/index')->where('providers.0.configured', true)->missing('providers.0.client_secret')->missing('providers.0.credential')->missing('providers.0.id'));
        [$viewer] = $this->panelAccount(MembershipRole::Viewer, setupComplete: true);
        $this->flushSession();
        $this->actingAs($viewer)->put(route('git.settings', 'github'), [])->assertForbidden();
    }

    public function test_github_installation_then_oauth_connects_and_dispatches_secretless_sync(): void
    {
        Queue::fake();
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $this->configureProvider($owner, 'github');
        $this->post(route('git.start', 'github'))->assertRedirect();
        $pending = session('git_oauth.github');
        $this->get(route('git.callback', ['provider' => 'github', 'state' => $pending['state'], 'installation_id' => '1234']))->assertRedirect();
        $authorized = session('git_oauth.github');
        $this->assertNotSame($pending['state'], $authorized['state']);
        Http::fake([
            'github.com/login/oauth/access_token' => Http::response(['access_token' => 'fixture-access', 'refresh_token' => 'fixture-refresh', 'expires_in' => 3600]),
            'api.github.com/user' => Http::response(['id' => 42, 'login' => 'fixture-user']),
            'api.github.com/user/installations?*' => Http::response(['installations' => [['id' => 1234, 'app_slug' => 'fixture-app', 'permissions' => ['contents' => 'read', 'metadata' => 'read'], 'account' => ['id' => 42, 'login' => 'fixture-user', 'type' => 'User']]]]),
            'api.github.com/user/installations/1234/repositories*' => Http::response(['repositories' => []]),
        ]);
        $this->get(route('git.callback', ['provider' => 'github', 'state' => $authorized['state'], 'code' => 'fixture-code']))->assertRedirect();
        $connection = GitConnection::query()->firstOrFail();
        $this->assertSame('github_app', $connection->auth_method->value);
        $this->assertSame('1234', $connection->installation_id);
        $this->assertSame($workspace->id, $connection->workspace_id);
        Queue::assertPushed(SyncGitRepositories::class, fn ($job) => $job->connectionUuid === $connection->uuid && ! str_contains(serialize($job), 'fixture-access'));
        $this->assertStringNotContainsString('fixture-access', AuditEvent::query()->get()->toJson());
        $this->get(route('git.show', $connection))->assertOk()->assertInertia(fn (Assert $page) => $page->missing('connection.credential')->missing('connection.id')->missing('connection.access_token'));
        $this->get(route('git.callback', ['provider' => 'github', 'state' => $authorized['state'], 'code' => 'replay']))->assertSessionHas('error');
        Http::assertSentCount(4);
    }

    public function test_gitlab_uses_pkce_and_bearer_token_and_disables_repositories_removed_upstream(): void
    {
        Queue::fake();
        [$owner] = $this->panelAccount(setupComplete: true);
        $this->configureProvider($owner, 'gitlab');
        $this->post(route('git.start', 'gitlab'))->assertRedirect();
        $pending = session('git_oauth.gitlab');
        Http::fake([
            'gitlab.com/oauth/token' => Http::response(['access_token' => 'fixture-gitlab-access', 'refresh_token' => 'fixture-gitlab-refresh', 'expires_in' => 7200]),
            'gitlab.com/api/v4/user' => Http::response(['id' => 99, 'username' => 'fixture-user']),
            'gitlab.com/api/v4/projects*' => Http::sequence()->push([['id' => 7, 'path_with_namespace' => 'team/repo', 'namespace' => ['path' => 'team'], 'http_url_to_repo' => 'https://gitlab.com/team/repo.git', 'default_branch' => 'main']])->push([]),
        ]);
        $this->get(route('git.callback', ['provider' => 'gitlab', 'state' => $pending['state'], 'code' => 'fixture-code']))->assertSessionHasNoErrors()->assertRedirect();
        Http::assertSent(fn ($req) => str_contains($req->url(), '/oauth/token') && $req['code_verifier'] === $pending['verifier']);
        $connection = GitConnection::query()->firstOrFail();
        app(GitConnector::class)->syncRepositories($connection, $owner, null, null);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/projects') && $req->hasHeader('Authorization', 'Bearer fixture-gitlab-access'));
        $repo = $connection->repositories()->firstOrFail();
        $this->assertFalse($repo->is_selected);
        $repo->forceFill(['is_selected' => true])->save();
        app(GitConnector::class)->syncRepositories($connection, $owner, null, null);
        $this->assertFalse($repo->fresh()->is_selected);
        $this->assertTrue($repo->fresh()->disabled);
    }

    public function test_repository_selection_and_revocation_are_workspace_scoped_and_audited(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $connection = GitConnection::factory()->create(['workspace_id' => $workspace->id]);
        $repo = RemoteRepository::factory()->create(['workspace_id' => $workspace->id, 'git_connection_id' => $connection->id]);
        $this->actingAs($owner)->put(route('git.select', $connection), ['visible_uuids' => [$repo->uuid], 'selected_uuids' => [$repo->uuid]])->assertRedirect();
        $this->assertTrue($repo->fresh()->is_selected);
        $this->assertDatabaseHas('audit_events', ['action' => 'git.repository_allowed', 'resource_id' => $connection->uuid]);
        [$foreign] = $this->panelAccount(setupComplete: true);
        $this->flushSession();
        $this->actingAs($foreign)->get(route('git.show', $connection))->assertNotFound();
        $this->actingAs($foreign)->put(route('git.select', $connection), [])->assertNotFound();
        $this->flushSession();
        $this->actingAs($owner)->delete(route('git.disconnect', $connection), ['confirm' => true])->assertRedirect();
        $this->assertSame(GitConnectionState::Revoked, $connection->fresh()->state);
        $this->assertFalse($repo->fresh()->is_selected);
        $this->assertNotNull($connection->credential->fresh()->revoked_at);
        $this->put(route('git.select', $connection), ['visible_uuids' => [$repo->uuid], 'selected_uuids' => [$repo->uuid]])->assertStatus(409);
    }

    public function test_sync_paginates_and_preserves_selection_but_failure_does_not_store_partial_catalog(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $connection = GitConnection::factory()->create(['workspace_id' => $workspace->id]);
        $payload = array_map(fn ($i) => ['id' => $i, 'full_name' => 'team/repo'.$i, 'clone_url' => 'https://github.com/team/repo'.$i.'.git', 'default_branch' => 'main'], range(1, 100));
        Http::fakeSequence()->push($payload)->push([['id' => 101, 'full_name' => 'team/last', 'clone_url' => 'https://github.com/team/last.git', 'default_branch' => 'main']]);
        $this->assertSame(101, app(GitConnector::class)->syncRepositories($connection, $owner, null, null));
        $repo = $connection->repositories()->firstOrFail();
        $repo->forceFill(['is_selected' => true])->save();
        Http::fakeSequence()->push($payload)->push([], 503);
        $job = new SyncGitRepositories($connection->uuid, $owner->uuid);
        try {
            $job->handle(app(GitConnector::class), app(AuditRecorder::class));
            $this->fail('A provider failure must fail the sync.');
        } catch (\RuntimeException $e) {
            $job->failed($e);
            $this->assertSame('Git repository synchronization failed.', $e->getMessage());
        }
        $this->assertTrue($repo->fresh()->is_selected);
        $this->assertSame(101, $connection->repositories()->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'git.sync_failed']);
    }

    public function test_expired_or_wrong_state_is_rejected_without_contacting_provider(): void
    {
        [$owner] = $this->panelAccount(setupComplete: true);
        $this->configureProvider($owner, 'gitlab');
        $this->post(route('git.start', 'gitlab'));
        $this->get(route('git.callback', ['provider' => 'gitlab', 'state' => 'wrong', 'code' => 'fixture']))->assertSessionHas('error');
        Http::assertNothingSent();
        $this->assertDatabaseCount('git_connections', 0);
    }

    public function test_expired_oauth_access_is_refreshed_without_exposing_rotated_material(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $this->configureProvider($owner, 'gitlab');
        $credential = app(CredentialStore::class)->store($workspace, $owner, 'gitlab_access', json_encode(['access_token' => 'expired-fixture', 'refresh_token' => 'refresh-fixture']));
        $connection = GitConnection::factory()->create([
            'workspace_id' => $workspace->id, 'provider' => 'gitlab', 'base_url' => 'https://gitlab.com',
            'auth_method' => 'oauth', 'credential_id' => $credential->id, 'expires_at' => now()->subMinute(),
        ]);
        Http::fake(['gitlab.com/oauth/token' => Http::response(['access_token' => 'renewed-fixture', 'refresh_token' => 'new-refresh-fixture', 'expires_in' => 7200])]);
        $this->assertSame('renewed-fixture', app(GitOAuthClient::class)->accessToken($connection, $owner));
        $this->assertTrue($connection->fresh()->expires_at->isFuture());
        $this->assertSame('renewed-fixture', app(GitOAuthClient::class)->accessToken($connection->fresh(), $owner));
        Http::assertSentCount(1);
        $this->assertStringNotContainsString('renewed-fixture', $credential->fresh()->toJson());
    }

    public function test_repository_selection_rejects_foreign_and_unavailable_rows_and_supports_granted_read_access(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $connection = GitConnection::factory()->create(['workspace_id' => $workspace->id]);
        $repo = RemoteRepository::factory()->create(['workspace_id' => $workspace->id, 'git_connection_id' => $connection->id, 'disabled' => true]);
        $this->actingAs($owner)->put(route('git.select', $connection), ['visible_uuids' => [$repo->uuid], 'selected_uuids' => [$repo->uuid]])->assertUnprocessable();
        $foreign = RemoteRepository::factory()->create();
        $this->put(route('git.select', $connection), ['visible_uuids' => [$foreign->uuid], 'selected_uuids' => [$foreign->uuid]])->assertUnprocessable();
        $viewer = User::factory()->create();
        UserProfile::factory()->create(['user_id' => $viewer->id, 'force_password_change' => false]);
        $membership = Membership::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $viewer->id, 'role' => 'viewer']);
        $this->flushSession();
        $this->actingAs($viewer)->get(route('git.show', $connection))->assertForbidden();
        ResourceGrant::factory()->create(['workspace_id' => $workspace->id, 'membership_id' => $membership->id, 'resource_type' => 'git_connection', 'resource_id' => $connection->id, 'permission' => 'view', 'created_by' => $owner->id]);
        $this->get(route('git.show', $connection))->assertOk();
        $this->put(route('git.select', $connection), ['visible_uuids' => [$repo->uuid], 'selected_uuids' => []])->assertForbidden();
    }

    public function test_disconnect_can_be_reconnected_without_reusing_old_repository_selections(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $connection = GitConnection::factory()->create(['workspace_id' => $workspace->id, 'external_account_id' => '42']);
        $repo = RemoteRepository::factory()->create(['workspace_id' => $workspace->id, 'git_connection_id' => $connection->id, 'is_selected' => true]);
        $this->actingAs($owner)->delete(route('git.disconnect', $connection), ['confirm' => true])->assertRedirect();
        Http::fake(['api.github.com/user' => Http::response(['id' => 42, 'login' => 'fixture-user'])]);
        $reconnected = app(GitConnector::class)->connect($workspace, $owner, GitProvider::Github, 'https://github.com', 'replacement-fixture-material', 'user', null, null);
        $this->assertSame($connection->uuid, $reconnected->uuid);
        $this->assertNotSame($connection->credential_id, $reconnected->credential_id);
        $this->assertFalse($repo->fresh()->is_selected);
        $this->assertSame(GitConnectionState::Active, $reconnected->state);
    }

    public function test_malformed_or_failed_provider_response_does_not_create_connection_or_expose_body(): void
    {
        Queue::fake();
        [$owner] = $this->panelAccount(setupComplete: true);
        $this->configureProvider($owner, 'gitlab');
        $this->post(route('git.start', 'gitlab'));
        $pending = session('git_oauth.gitlab');
        Http::fake(['gitlab.com/oauth/token' => Http::response(['error_description' => 'fixture-private-provider-detail'], 429)]);
        $response = $this->get(route('git.callback', ['provider' => 'gitlab', 'state' => $pending['state'], 'code' => 'fixture-code']));
        $response->assertSessionHas('error');
        $this->assertStringNotContainsString('fixture-private-provider-detail', session('error'));
        $this->assertDatabaseCount('git_connections', 0);
        Queue::assertNothingPushed();
        $this->assertDatabaseHas('audit_events', ['action' => 'git.authorization_failed', 'result' => 'failure']);
    }

    public function test_github_rejects_installation_with_write_permissions(): void
    {
        Queue::fake();
        [$owner] = $this->panelAccount(setupComplete: true);
        $this->configureProvider($owner, 'github');
        $this->post(route('git.start', 'github'));
        $pending = session('git_oauth.github');
        $this->get(route('git.callback', ['provider' => 'github', 'state' => $pending['state'], 'installation_id' => '1234']));
        $pending = session('git_oauth.github');
        Http::fake([
            'github.com/login/oauth/access_token' => Http::response(['access_token' => 'fixture-access']),
            'api.github.com/user' => Http::response(['id' => 42, 'login' => 'fixture-user']),
            'api.github.com/user/installations?*' => Http::response(['installations' => [['id' => 1234, 'app_slug' => 'fixture-app', 'permissions' => ['contents' => 'write', 'metadata' => 'read'], 'account' => ['id' => 42, 'login' => 'fixture-user']]]]),
        ]);
        $this->get(route('git.callback', ['provider' => 'github', 'state' => $pending['state'], 'code' => 'fixture-code']))->assertSessionHas('error');
        $this->assertDatabaseCount('git_connections', 0);
        Queue::assertNothingPushed();
    }

    public function test_expired_authorization_is_rejected_without_provider_request(): void
    {
        [$owner] = $this->panelAccount(setupComplete: true);
        $this->configureProvider($owner, 'gitlab');
        $this->post(route('git.start', 'gitlab'));
        $pending = session('git_oauth.gitlab');
        $pending['expires'] = time() - 1;
        $this->withSession(['git_oauth' => ['gitlab' => $pending]])
            ->get(route('git.callback', ['provider' => 'gitlab', 'state' => $pending['state'], 'code' => 'fixture-code']))->assertSessionHas('error');
        Http::assertNothingSent();
        $this->assertDatabaseCount('git_connections', 0);
    }

    public function test_unselected_repository_cannot_read_branches_or_queue_deployment(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $connection = GitConnection::factory()->create(['workspace_id' => $workspace->id]);
        $repo = RemoteRepository::factory()->create(['workspace_id' => $workspace->id, 'git_connection_id' => $connection->id]);
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id, 'remote_repository_id' => $repo->id]);
        $environment = Environment::factory()->create(['project_id' => $project->id]);
        try {
            app(GitConnector::class)->syncBranches($repo, $owner, null, null);
            $this->fail('Unselected repository must not read branches.');
        } catch (GitRejected) {
            Http::assertNothingSent();
        }
        try {
            app(DeploymentWorkflow::class)->queueManual($environment, $owner, str_repeat('a', 40), null, null);
            $this->fail('Unselected repository must not queue deployment.');
        } catch (DeploymentRejected) {
            $this->assertDatabaseCount('deployments', 0);
            $this->assertDatabaseCount('operation_jobs', 0);
        }
    }

    public function test_three_provider_options_and_one_click_redirects_are_available(): void
    {
        [$owner] = $this->panelAccount(setupComplete: true);
        $this->configureProvider($owner, 'github');
        $this->get(route('git.index'))->assertInertia(fn (Assert $page) => $page->has('providers', 3)->where('providers.2.provider', 'gitlabSelf'));
        $github = $this->post(route('git.start', 'github'), [], ['X-Inertia' => 'true'])->assertStatus(409);
        $this->assertStringStartsWith('https://github.com/apps/fixture-app/installations/new?state=', $github->headers->get('X-Inertia-Location'));
        $this->post(route('git.start', 'gitlab'))->assertRedirect()->assertSessionHas('warning');
        $this->configureProvider($owner, 'gitlab');
        $response = $this->post(route('git.start', 'gitlab'), [], ['X-Inertia' => 'true'])->assertStatus(409);
        $this->assertStringStartsWith('https://gitlab.com/oauth/authorize?', $response->headers->get('X-Inertia-Location'));
    }

    public function test_self_managed_gitlab_uses_only_its_configured_origin_for_oauth_api_and_refresh(): void
    {
        Queue::fake();
        [$owner] = $this->panelAccount(setupComplete: true);
        $this->actingAs($owner)->put(route('git.settings', 'gitlabSelf'), [
            'base_url' => 'https://git.example.com/', 'client_id' => 'self-client', 'client_secret' => 'self-fixture-secret',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $response = $this->post(route('git.start', 'gitlabSelf'), [], ['X-Inertia' => 'true'])->assertStatus(409);
        $this->assertStringStartsWith('https://git.example.com/oauth/authorize?', $response->headers->get('X-Inertia-Location'));
        $pending = session('git_oauth.gitlabSelf');
        Http::fake([
            'git.example.com/oauth/token' => Http::sequence()->push(['access_token' => 'self-access', 'refresh_token' => 'self-refresh', 'expires_in' => 3600])->push(['access_token' => 'self-renewed', 'refresh_token' => 'self-refresh-new', 'expires_in' => 3600]),
            'git.example.com/api/v4/user' => Http::response(['id' => 45, 'username' => 'self-user']),
            'git.example.com/api/v4/projects*' => Http::response([]),
        ]);
        $this->get(route('git.callback', ['provider' => 'gitlabSelf', 'state' => $pending['state'], 'code' => 'self-code']))->assertRedirect()->assertSessionMissing('error');
        $connection = GitConnection::query()->firstOrFail();
        $this->assertSame('gitlab', $connection->provider->value);
        $this->assertSame('https://git.example.com', $connection->base_url);
        app(GitConnector::class)->syncRepositories($connection, $owner, null, null);
        $connection->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->assertSame('self-renewed', app(GitOAuthClient::class)->accessToken($connection, $owner));
        Http::assertNotSent(fn ($r) => parse_url($r->url(), PHP_URL_HOST) !== 'git.example.com');
        $this->get(route('git.show', $connection))->assertInertia(fn (Assert $page) => $page->where('connection.provider', 'gitlabSelf')->where('manage_url', 'https://git.example.com/-/user_settings/applications'));
    }

    public function test_self_managed_origin_rejects_internal_urls_paths_and_dns_rebinding(): void
    {
        [$owner] = $this->panelAccount(setupComplete: true);
        foreach (['http://git.example.com', 'https://127.0.0.1', 'https://10.0.0.1', 'https://user:pass@git.example.com', 'https://git.example.com/path', 'https://git.example.com:8443', 'https://gitlab.com'] as $url) {
            $this->actingAs($owner)->put(route('git.settings', 'gitlabSelf'), ['base_url' => $url, 'client_id' => 'self-client', 'client_secret' => 'self-fixture-secret'])->assertSessionHasErrors('base_url');
        }
        $this->assertDatabaseCount('git_provider_settings', 0);
        $this->app->instance(HostAddressResolver::class, new class implements HostAddressResolver
        {
            public function addresses(string $host): array
            {
                return ['127.0.0.1'];
            }
        });
        $this->put(route('git.settings', 'gitlabSelf'), ['base_url' => 'https://git.example.com', 'client_id' => 'self-client', 'client_secret' => 'self-fixture-secret'])->assertSessionHasErrors('base_url');
        $this->assertDatabaseCount('git_provider_settings', 0);
        Http::assertNothingSent();
    }

    public function test_manual_probe_does_not_store_material_and_manual_connect_is_encrypted(): void
    {
        Queue::fake();
        [$owner] = $this->panelAccount(setupComplete: true);
        Http::fake([
            'api.github.com/user' => Http::response(['id' => 88, 'login' => 'manual-user']),
            'api.github.com/user/repos*' => Http::response([]),
        ]);
        $this->actingAs($owner)->post(route('git.provider-test', 'github'), ['mode' => 'manual', 'access_token' => 'manual-fixture-token'])->assertSessionHas('status');
        $this->assertDatabaseCount('credentials', 0);
        $this->assertDatabaseCount('git_connections', 0);
        $this->assertStringNotContainsString('manual-fixture-token', AuditEvent::query()->get()->toJson());
        $this->post(route('git.manual', 'github'), ['access_token' => 'manual-fixture-token'])->assertRedirect();
        $connection = GitConnection::query()->firstOrFail();
        $this->assertSame('manual-user', $connection->account_label);
        $this->assertSame('personal_access_token', $connection->auth_method->value);
        $this->assertStringNotContainsString('manual-fixture-token', json_encode($connection->credential->envelope));
        Queue::assertPushed(SyncGitRepositories::class);
    }

    public function test_existing_account_can_be_tested_and_test_does_not_change_repository_selection(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $connection = GitConnection::factory()->create(['workspace_id' => $workspace->id, 'external_account_id' => '44']);
        $repo = RemoteRepository::factory()->create(['workspace_id' => $workspace->id, 'git_connection_id' => $connection->id, 'is_selected' => true]);
        Http::fake(['api.github.com/user' => Http::response(['id' => 44, 'login' => 'test-user']), 'api.github.com/user/repos*' => Http::response([])]);
        $this->actingAs($owner)->post(route('git.test', $connection))->assertSessionHas('status');
        $this->assertTrue($repo->fresh()->is_selected);
        $this->assertDatabaseHas('audit_events', ['action' => 'git.connection_test', 'result' => 'success']);
        $connection->forceFill(['state' => GitConnectionState::Revoked, 'revoked_at' => now()])->save();
        $this->post(route('git.test', $connection))->assertStatus(409);
        Http::assertSentCount(2);
    }

    public function test_automatic_preflight_tests_only_authorization_page_and_has_honest_result(): void
    {
        [$owner] = $this->panelAccount(setupComplete: true);
        $this->configureProvider($owner, 'gitlab');
        Http::fake(['gitlab.com/oauth/authorize*' => Http::response('', 302, ['Location' => 'https://gitlab.com/users/sign_in'])]);
        $this->post(route('git.provider-test', 'gitlab'), ['mode' => 'automatic'])->assertSessionHas('info');
        $this->assertDatabaseCount('git_connections', 0);
        Http::assertSent(fn ($request) => ! $request->hasHeader('Authorization') && ! str_contains($request->url(), 'fixture-client-secret'));
        $this->assertDatabaseHas('audit_events', ['action' => 'git.oauth_preflight', 'result' => 'success']);
    }

    public function test_failed_manual_probe_and_unauthorized_test_never_expose_material(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        Http::fake(['gitlab.com/api/v4/user' => Http::response(['error' => 'private-fixture-body'], 401)]);
        $this->actingAs($owner)->post(route('git.provider-test', 'gitlab'), ['mode' => 'manual', 'access_token' => 'private-fixture-token'])->assertSessionHas('error');
        $this->assertStringNotContainsString('private-fixture', session('error'));
        $this->assertDatabaseCount('credentials', 0);
        $this->assertDatabaseHas('audit_events', ['action' => 'git.manual_test', 'result' => 'failure']);
        $connection = GitConnection::factory()->create(['workspace_id' => $workspace->id]);
        [$foreign] = $this->panelAccount(setupComplete: true);
        $this->flushSession();
        $this->actingAs($foreign)->post(route('git.test', $connection))->assertNotFound();
        Http::assertSentCount(1);
    }
}
