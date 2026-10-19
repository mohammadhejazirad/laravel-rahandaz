<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\RecordOperation;
use App\Models\LocalHost;
use App\Models\MirrorEndpoint;
use App\Models\PackageDefinition;
use App\Models\PanelNotification;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class PanelPagesTest extends TestCase
{
    use CreatesPanelAccount, RefreshDatabase;

    public function test_http_ip_forms_receive_server_generated_request_keys(): void
    {
        [$owner] = $this->panelAccount(setupComplete: true);
        foreach (['deployments.index', 'backups.index'] as $route) {
            $this->actingAs($owner)->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('request_key', fn ($key) => is_string($key) && Str::isUuid($key)));
        }
    }

    public function test_workspace_pages_list_real_rows_without_numeric_ids(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id, 'slug' => 'local-host']);
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'local_host_id' => $host->id,
            'created_by' => $owner->id,
        ]);
        $job = app(RecordOperation::class)->record(new RecordOperationData(
            workspaceId: $workspace->id,
            actorId: $owner->id,
            type: 'host.noop',
            idempotencyKey: 'panel-pages',
            plan: ['target' => 'none'],
            mutatesHost: false,
        ));
        $package = PackageDefinition::factory()->create(['display_name' => 'Nginx']);
        $mirror = MirrorEndpoint::factory()->create();
        PanelNotification::query()->create([
            'workspace_id' => $workspace->id,
            'recipient_id' => $owner->id,
            'type' => 'panel.test',
            'level' => 'info',
            'title' => 'یک اعلان',
            'body' => 'متن بدون راز',
            'event_key' => 'panel-pages-note',
        ]);

        [$other, $otherWorkspace] = $this->panelAccount(setupComplete: true);
        $foreignHost = LocalHost::factory()->create(['workspace_id' => $otherWorkspace->id, 'slug' => 'local-host']);
        Project::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'local_host_id' => $foreignHost->id,
            'created_by' => $other->id,
            'slug' => 'foreign-project',
        ]);

        $this->actingAs($owner)->get(route('projects.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('projects/index')
            ->where('create_available', true)
            ->has('projects.data', 1)
            ->where('projects.data.0.uuid', $project->uuid)
            ->where('projects.data.0.name', $project->name)
            ->missing('projects.data.0.id'));

        $this->actingAs($owner)->get(route('projects.show', $project))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('projects/show')
            ->where('deploy_available', false)
            ->where('project.uuid', $project->uuid)
            ->missing('project.id'));

        $this->actingAs($owner)->get(route('servers.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('servers/index')
            ->has('hosts.data', 1)
            ->where('hosts.data.0.uuid', $host->uuid)
            ->missing('hosts.data.0.id'));

        $this->actingAs($owner)->get(route('servers.show', $foreignHost))->assertNotFound();

        $storedStatus = ($job->fresh() ?? $job)->status->value;

        $this->actingAs($owner)->get(route('jobs.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('jobs/index')
            ->where('jobs.data.0.uuid', $job->uuid)
            ->where('jobs.data.0.type', 'host.noop')
            ->where('jobs.data.0.status', $storedStatus)
            ->where('enum_labels.'.$storedStatus, JobStatus::from($storedStatus)->label())
            ->missing('jobs.data.0.id')
            ->missing('jobs.data.0.plan'));

        $this->actingAs($owner)->get(route('jobs.show', $job))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('jobs/show')
            ->where('cancel_available', false)
            ->where('job.uuid', $job->uuid));

        $this->actingAs($owner)->get(route('catalog.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('catalog/index')
            ->where('install_available', false)
            ->where('packages.data.0.display_name', $package->display_name)
            ->missing('packages.data.0.id'));

        $this->actingAs($owner)->get(route('mirrors.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('mirrors/index')
            ->where('apply_available', false)
            ->where('mirrors.data.0.uuid', $mirror->uuid)
            ->missing('mirrors.data.0.id'));

        $this->actingAs($owner)->get(route('notifications.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->where('notifications.data.0.title', 'یک اعلان')
            ->where('notifications.data.0.read_at', null)
            ->missing('notifications.data.0.id'));

        $this->actingAs($owner)->get(route('audit.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('audit/index')
            ->has('events.data', 0));

        $this->actingAs($owner)->get(route('settings.profile'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('mfa_available', false)
            ->where('profile.username', $owner->username)
            ->where('profile.theme', $owner->theme->value)
            ->where('auth.user.role', 'owner')
            ->where('enum_labels.owner', 'مالک')
            ->where('enum_labels.'.$owner->theme->value, $owner->theme->label())
            ->missing('profile.id'));
    }

    public function test_incomplete_setup_cannot_open_the_new_pages(): void
    {
        [$owner] = $this->panelAccount();

        $this->actingAs($owner)->get(route('projects.index'))->assertRedirect(route('setup.show'));
    }

    public function test_viewer_cannot_read_the_audit_log(): void
    {
        [$viewer] = $this->panelAccount(MembershipRole::Viewer, setupComplete: true);

        $this->actingAs($viewer)->get(route('audit.index'))->assertForbidden();
    }

    public function test_notification_read_is_limited_to_the_recipient(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        [$other] = $this->panelAccount(setupComplete: true);
        $notification = PanelNotification::query()->create([
            'workspace_id' => $workspace->id,
            'recipient_id' => $owner->id,
            'type' => 'panel.test',
            'level' => 'info',
            'title' => 'خصوصی',
            'body' => 'فقط گیرنده',
            'event_key' => 'panel-pages-private',
        ]);

        $this->actingAs($other)->post(route('notifications.read', $notification))->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);

        $this->flushSession();
        $this->actingAs($owner)
            ->from(route('notifications.index'))
            ->post(route('notifications.read', $notification))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('status', 'اعلان به‌عنوان خوانده‌شده علامت خورد.');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_job_from_another_workspace_is_not_listed(): void
    {
        [$owner] = $this->panelAccount(setupComplete: true);
        [$other, $otherWorkspace] = $this->panelAccount(setupComplete: true);
        $foreign = app(RecordOperation::class)->record(new RecordOperationData(
            workspaceId: $otherWorkspace->id,
            actorId: $other->id,
            type: 'host.noop',
            idempotencyKey: 'foreign-job',
            plan: ['target' => 'none'],
            mutatesHost: false,
        ));

        $this->actingAs($owner)->get(route('jobs.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('jobs.data', 0));

        $this->actingAs($owner)->get(route('jobs.show', $foreign))->assertForbidden();
    }
}
