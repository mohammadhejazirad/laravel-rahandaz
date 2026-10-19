<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Git\Enums\GitProvider;
use App\Infrastructure\Http\HostAddressResolver;
use App\Models\Deployment;
use App\Models\GitConnection;
use App\Models\LocalHost;
use App\Models\OperationJob;
use App\Models\Project;
use App\Models\RemoteRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class ProjectSourceLaunchTest extends TestCase
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

    public function test_owner_picks_one_of_several_accounts_then_repository_branch_and_queues_a_deployment(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id, 'display_name' => 'میزبان اصلی']);
        $github = GitConnection::factory()->create([
            'workspace_id' => $workspace->id,
            'account_label' => 'first-user',
            'external_account_id' => '101',
        ]);
        GitConnection::factory()->create([
            'workspace_id' => $workspace->id,
            'provider' => GitProvider::Gitlab,
            'account_label' => 'self-user',
            'external_account_id' => '202',
            'base_url' => 'https://git.example.com',
        ]);
        $repository = RemoteRepository::factory()->create([
            'workspace_id' => $workspace->id,
            'git_connection_id' => $github->id,
            'full_name' => 'acme/shop',
            'default_branch' => 'main',
            'is_selected' => false,
        ]);
        $sha = str_repeat('ab', 20);

        $this->actingAs($owner)->get(route('projects.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('projects/create')
            ->has('accounts', 2)
            ->where('accounts.0.account_label', 'self-user')
            ->where('accounts.0.provider', 'gitlabSelf')
            ->where('accounts.1.provider', 'github')
            ->where('execution_mode', 'disabled'));

        $this->get(route('projects.create', ['connection' => $github->uuid]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('connection', $github->uuid)
            ->has('repositories.data', 1)
            ->where('repositories.data.0.full_name', 'acme/shop'));

        Http::fake(['api.github.com/repos/acme/shop/branches*' => Http::response([[
            'name' => 'main',
            'protected' => false,
            'commit' => ['sha' => $sha],
        ]])]);
        $this->post(route('projects.branches', $repository))->assertRedirect(route('projects.create', [
            'connection' => $github->uuid,
            'repository' => $repository->uuid,
        ]));
        $this->assertTrue($repository->fresh()->is_selected);
        $this->assertSame($sha, $repository->branches()->where('name', 'main')->value('head_sha'));

        $this->post(route('projects.store'), [
            'repository' => $repository->uuid,
            'branch' => 'main',
            'name' => 'فروشگاه',
            'declared_type' => 'laravel',
            'host' => $host->uuid,
        ])->assertRedirect();

        $project = Project::query()->where('workspace_id', $workspace->id)->where('slug', 'shop')->firstOrFail();
        $this->assertSame($repository->id, $project->remote_repository_id);
        $this->assertSame('main', $project->default_branch);
        $this->assertSame('تولید', $project->environments()->value('name'));
        $deployment = Deployment::query()->where('workspace_id', $workspace->id)->firstOrFail();
        $this->assertSame($sha, $deployment->commit_sha);
        $this->assertFalse(OperationJob::query()->findOrFail($deployment->operation_job_id)->plan['input']['host_executed']);
        $this->assertDatabaseHas('audit_events', ['action' => 'project.launch', 'result' => 'success']);
        $this->get(route('projects.index'))->assertInertia(fn (Assert $page) => $page->where('create_available', true)->where('projects.data.0.uuid', $project->uuid));
    }

    public function test_branch_fetch_failure_does_not_keep_a_new_repository_selection(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $connection = GitConnection::factory()->create(['workspace_id' => $workspace->id]);
        $repository = RemoteRepository::factory()->create([
            'workspace_id' => $workspace->id,
            'git_connection_id' => $connection->id,
            'full_name' => 'acme/shop',
            'is_selected' => false,
        ]);
        Http::fake(['api.github.com/*' => Http::response(['message' => 'no'], 403)]);
        $this->actingAs($owner)->post(route('projects.branches', $repository))->assertSessionHas('error');
        $this->assertFalse($repository->fresh()->is_selected);
        $this->assertDatabaseCount('remote_branches', 0);
    }

    public function test_launch_rejects_other_workspaces_operators_and_missing_hosts(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        [, $otherWorkspace] = $this->panelAccount(setupComplete: true);
        $foreign = RemoteRepository::factory()->create(['workspace_id' => $otherWorkspace->id, 'full_name' => 'acme/foreign']);
        $connection = GitConnection::factory()->create(['workspace_id' => $workspace->id]);
        $repository = RemoteRepository::factory()->create([
            'workspace_id' => $workspace->id,
            'git_connection_id' => $connection->id,
            'full_name' => 'acme/shop',
            'is_selected' => true,
        ]);

        $this->actingAs($owner)->post(route('projects.branches', $foreign))->assertNotFound();
        $this->actingAs($owner)->post(route('projects.store'), [
            'repository' => $repository->uuid,
            'branch' => 'main',
            'name' => 'فروشگاه',
            'declared_type' => 'laravel',
            'host' => LocalHost::factory()->create(['workspace_id' => $otherWorkspace->id, 'slug' => 'other-host'])->uuid,
        ])->assertSessionHas('error');
        $this->assertDatabaseCount('projects', 0);
        $this->assertNotSame($workspace->uuid, $otherWorkspace->uuid);
    }

    public function test_operator_cannot_open_the_launch_page(): void
    {
        [$operator] = $this->panelAccount(MembershipRole::Operator, setupComplete: true);
        $this->actingAs($operator)->get(route('projects.create'))->assertForbidden();
    }
}
