<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Git\Enums\GitProvider;
use App\Domain\Git\GitConnector;
use App\Domain\Git\RepositoryActivity;
use App\Infrastructure\Http\HostAddressResolver;
use App\Models\GitConnection;
use App\Models\RemoteRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class RepositoryActivityTest extends TestCase
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
    }

    public function test_sync_stores_latest_remote_change_or_creation_for_both_providers(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        foreach ([GitProvider::Github, GitProvider::Gitlab] as $provider) {
            $github = $provider === GitProvider::Github;
            $connection = GitConnection::factory()->create(['workspace_id' => $workspace->id, 'provider' => $provider, 'base_url' => $github ? 'https://github.com' : 'https://gitlab.com']);
            $row = $github
                ? ['id' => 12, 'full_name' => 'team/recent', 'clone_url' => 'https://github.com/team/recent.git', 'pushed_at' => '2026-10-29T12:00:00Z']
                : ['id' => 12, 'path_with_namespace' => 'team/recent', 'http_url_to_repo' => 'https://gitlab.com/team/recent.git', 'last_activity_at' => '2026-10-29T12:00:00Z'];
            $row += ['created_at' => '2026-01-01T00:00:00Z', 'updated_at' => '2026-10-30T12:00:00Z'];
            $nextRow = [...$row, 'created_at' => '2026-10-31T10:00:00Z'];
            Http::fake([$github ? 'api.github.com/user/repos*' : 'gitlab.com/api/v4/projects*' => Http::sequence()->push([$row])->push([$nextRow])]);
            $this->assertSame(1, app(GitConnector::class)->syncRepositories($connection, $owner, null, null));
            $repository = $connection->repositories()->firstOrFail();
            $this->assertSame('2026-10-30T12:00:00+00:00', $repository->provider_activity_at->toIso8601String());
            app(GitConnector::class)->syncRepositories($connection, $owner, null, null);
            $this->assertSame($repository->id, $connection->repositories()->firstOrFail()->id);
            $this->assertSame('2026-10-31T10:00:00+00:00', $repository->fresh()->provider_activity_at->toIso8601String());
        }
    }

    public function test_both_repository_pages_sort_before_pagination_and_limit_and_ignore_local_edits(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $connection = GitConnection::factory()->create(['workspace_id' => $workspace->id]);
        RemoteRepository::factory()->count(201)->create(['workspace_id' => $workspace->id, 'git_connection_id' => $connection->id, 'provider_activity_at' => '2026-01-01T00:00:00Z']);
        $recent = RemoteRepository::factory()->create(['workspace_id' => $workspace->id, 'git_connection_id' => $connection->id, 'full_name' => 'zzzz/latest', 'provider_activity_at' => '2026-10-30T00:00:00Z']);
        $unknown = RemoteRepository::factory()->create(['workspace_id' => $workspace->id, 'git_connection_id' => $connection->id, 'full_name' => 'aaaa/unknown', 'provider_activity_at' => null]);
        $unknown->forceFill(['is_selected' => true, 'updated_at' => now()->addDay()])->save();
        $this->actingAs($owner)->get(route('git.show', $connection))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('repositories.data', 50)->where('repositories.data.0.uuid', $recent->uuid));
        $this->get(route('projects.create', ['connection' => $connection->uuid]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('repositories.data', 200)->where('repositories.truncated', true)->where('repositories.data.0.uuid', $recent->uuid));
        $this->get(route('git.show', ['connection' => $connection, 'search' => 'unknown']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('repositories.data', 1)->where('repositories.data.0.uuid', $unknown->uuid));
    }

    public function test_invalid_or_absent_remote_dates_never_become_sync_time(): void
    {
        $this->assertNull(RepositoryActivity::latest(['updated_at' => 'not-a-date', 'created_at' => []]));
        $this->assertNull(RepositoryActivity::latest(['updated_at' => '2026-02-31T00:00:00Z']));
        $this->assertSame('2026-10-31T10:00:00.000000+00:00', RepositoryActivity::latest(['pushed_at' => '2026-10-31T11:00:00+03:30', 'created_at' => '2026-10-31T10:00:00Z']));
    }
}
