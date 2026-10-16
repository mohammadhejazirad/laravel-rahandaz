<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\RecordOperation;
use App\Models\LocalHost;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class PanelExperienceTest extends TestCase
{
    use CreatesPanelAccount, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.ssr.enabled' => false]);
    }

    public function test_dashboard_recent_items_are_bounded_workspace_scoped_and_public_only(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id]);
        Project::factory()->count(8)->create(['workspace_id' => $workspace->id, 'local_host_id' => $host->id, 'created_by' => $owner->id]);
        for ($i = 0; $i < 8; $i++) {
            app(RecordOperation::class)->record(new RecordOperationData(
                workspaceId: $workspace->id, actorId: $owner->id, type: 'host.noop', idempotencyKey: 'experience-'.$i,
                plan: ['private_parameter' => 'not-for-dashboard'], mutatesHost: false,
            ));
        }
        [$other, $foreign] = $this->panelAccount(setupComplete: true);
        $foreignHost = LocalHost::factory()->create(['workspace_id' => $foreign->id]);
        Project::factory()->create(['workspace_id' => $foreign->id, 'local_host_id' => $foreignHost->id, 'created_by' => $other->id, 'name' => 'foreign-project']);
        app(RecordOperation::class)->record(new RecordOperationData(workspaceId: $foreign->id, actorId: $other->id, type: 'foreign.noop', idempotencyKey: 'foreign', plan: [], mutatesHost: false));
        $latest = Project::query()->forWorkspace($workspace->id)->latest('id')->firstOrFail();
        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('recent_projects', 6)->has('recent_jobs', 6)->where('inventory.projects', 8)
            ->where('recent_projects.0.uuid', $latest->uuid)->where('recent_jobs.0.type', 'host.noop')
            ->missing('recent_projects.0.id')->missing('recent_projects.0.workspace_id')
            ->missing('recent_jobs.0.id')->missing('recent_jobs.0.plan')->missing('recent_jobs.0.payload'))
            ->assertDontSee('foreign-project')->assertDontSee('not-for-dashboard');
    }

    public function test_project_search_matches_name_or_slug_literally_and_never_other_workspaces(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id]);
        $match = Project::factory()->create(['workspace_id' => $workspace->id, 'local_host_id' => $host->id, 'created_by' => $owner->id, 'name' => 'Sample Site', 'slug' => 'my-project']);
        Project::factory()->create(['workspace_id' => $workspace->id, 'local_host_id' => $host->id, 'created_by' => $owner->id, 'name' => 'other', 'slug' => 'other']);
        $this->actingAs($owner)->get(route('projects.index', ['q' => 'SAMPLE']))->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 1)->where('projects.data.0.uuid', $match->uuid)->where('filters.q', 'SAMPLE'));
        $this->get(route('projects.index', ['q' => 'my-project']))->assertInertia(fn (Assert $page) => $page->has('projects.data', 1));
        $this->get(route('projects.index', ['q' => '%']))->assertInertia(fn (Assert $page) => $page->has('projects.data', 0));
        $this->get(route('projects.index', ['q' => str_repeat('x', 121)]))->assertSessionHasErrors('q');
    }

    public function test_job_filter_validates_status_and_preserves_workspace_scope(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        app(RecordOperation::class)->record(new RecordOperationData(workspaceId: $workspace->id, actorId: $owner->id, type: 'host.noop', idempotencyKey: 'queued', plan: [], mutatesHost: false));
        $this->actingAs($owner)->get(route('jobs.index', ['status' => 'queued']))->assertInertia(fn (Assert $page) => $page
            ->has('jobs.data', 1)->where('jobs.data.0.status', 'queued')->where('filters.status', 'queued'));
        $this->get(route('jobs.index', ['status' => 'failed']))->assertInertia(fn (Assert $page) => $page->has('jobs.data', 0));
        $this->get(route('jobs.index', ['status' => 'unknown']))->assertSessionHasErrors('status');
    }
}
