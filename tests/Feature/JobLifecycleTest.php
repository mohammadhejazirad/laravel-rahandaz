<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\OperationRunner;
use App\Domain\Jobs\RecordOperation;
use App\Models\PanelNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class JobLifecycleTest extends TestCase
{
    use CreatesPanelAccount, RefreshDatabase;

    public function test_redelivery_does_not_duplicate_audited_failure_notifications(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $job = app(RecordOperation::class)->record(new RecordOperationData(workspaceId: $workspace->id, actorId: $owner->id, type: 'host.noop', idempotencyKey: 'lifecycle-test', plan: ['target' => 'none'], mutatesHost: false));
        app(OperationRunner::class)->run($job->id);
        app(OperationRunner::class)->run($job->id);
        $this->assertSame(2, PanelNotification::query()->where('recipient_id', $owner->id)->count());
        $this->assertDatabaseCount('audit_events', 2);
        $this->assertDatabaseHas('panel_notifications', ['type' => 'operation.failed', 'workspace_id' => $workspace->id]);
        $this->assertDatabaseHas('panel_notifications', ['type' => 'operation.failed', 'action_path' => '/rahandaz/jobs/'.$job->uuid]);
        $this->assertSame(0, PanelNotification::query()->where('type', 'operation.succeeded')->count());
    }

    public function test_suspended_account_cannot_keep_using_an_authenticated_session(): void
    {
        [$owner] = $this->panelAccount(setupComplete: true);
        $this->actingAs($owner)->get(route('tutorials.index'))->assertOk();
        $owner->status = 'suspended';
        $owner->save();
        $this->get(route('tutorials.index'))->assertForbidden();
        $this->assertGuest();
    }
}
