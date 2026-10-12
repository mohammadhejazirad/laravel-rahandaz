<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Exceptions\IdempotencyConflict;
use App\Domain\Jobs\Exceptions\LeaseConflict;
use App\Domain\Jobs\Exceptions\UnsafePlan;
use App\Domain\Jobs\LeaseManager;
use App\Domain\Jobs\OperationRunner;
use App\Domain\Jobs\OutboxDispatcher;
use App\Domain\Jobs\RecordOperation;
use App\Infrastructure\Executor\ExecutionGate;
use App\Models\ExecutionLease;
use App\Models\OperationJob;
use App\Models\OperationJobOutbox;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class OperationEngineTest extends TestCase
{
    use CreatesPanelAccount, RefreshDatabase;

    public function test_idempotency_returns_the_same_job_and_rejects_a_different_payload(): void
    {
        [$user, $workspace] = $this->panelAccount();
        $recorder = app(RecordOperation::class);
        $first = $recorder->record($this->operation($workspace->id, $user->id, 'same', ['target' => 'none']));
        $second = $recorder->record($this->operation($workspace->id, $user->id, 'same', ['target' => 'none']));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, OperationJob::query()->count());
        $this->assertSame(1, OperationJobOutbox::query()->count());

        try {
            $recorder->record($this->operation($workspace->id, $user->id, 'same', ['target' => 'other']));
            $this->fail('A different payload reused an idempotency key.');
        } catch (IdempotencyConflict) {
            $this->assertSame(1, OperationJob::query()->count());
        }
    }

    public function test_unsafe_plan_is_rejected_before_insert(): void
    {
        [$user, $workspace] = $this->panelAccount();

        try {
            app(RecordOperation::class)->record($this->operation($workspace->id, $user->id, 'unsafe', [
                'note' => 'Bearer raw-token',
            ]));
            $this->fail('A secret-like plan was stored.');
        } catch (UnsafePlan) {
            $this->assertSame(0, OperationJob::query()->count());
        }
    }

    public function test_runner_fail_closes_and_never_marks_success(): void
    {
        [$user, $workspace] = $this->panelAccount();
        $job = app(RecordOperation::class)->record($this->operation($workspace->id, $user->id, 'run', ['target' => 'none']));

        $this->assertFalse(app(ExecutionGate::class)->allowsHostMutation());
        $this->assertSame(1, app(OutboxDispatcher::class)->publishPending());
        $this->assertSame(0, app(OutboxDispatcher::class)->publishPending());

        $job->refresh();
        $this->assertSame(JobStatus::Failed, $job->status);
        $this->assertSame('EXECUTION_DISABLED', $job->error_code);
        $this->assertSame(0, OperationJob::query()->where('status', JobStatus::Succeeded)->count());
        $this->assertNotNull($job->logs()->value('message'));
        $this->assertStringNotContainsString('sudo', (string) $job->logs()->value('message'));

        app(OperationRunner::class)->run($job->id);
        $this->assertSame(JobStatus::Failed, $job->fresh()->status);
    }

    public function test_local_mode_still_has_no_executor(): void
    {
        config(['rahandaz.execution_mode' => 'local']);
        [$user, $workspace] = $this->panelAccount();
        $job = app(RecordOperation::class)->record($this->operation($workspace->id, $user->id, 'local', ['target' => 'none']));

        app(OperationRunner::class)->run($job->id);

        $this->assertSame('EXECUTOR_NOT_CONFIGURED', $job->fresh()->error_code);
        $this->assertNotSame(JobStatus::Succeeded, $job->fresh()->status);
    }

    public function test_expired_lease_is_not_stolen_and_one_active_lease_is_enforced(): void
    {
        [$user, $workspace] = $this->panelAccount();
        $recorder = app(RecordOperation::class);
        $first = $recorder->record($this->operation($workspace->id, $user->id, 'lease-a', ['target' => 'none']));
        $second = $recorder->record($this->operation($workspace->id, $user->id, 'lease-b', ['target' => 'none']));
        $manager = app(LeaseManager::class);

        $lease = $manager->acquire('resource:panel', $first->id, 1, 30);
        $lease->forceFill(['expires_at' => now()->subMinute()])->save();

        try {
            $manager->acquire('resource:panel', $second->id, 1, 30);
            $this->fail('An expired lease was taken over.');
        } catch (LeaseConflict) {
            $this->assertNull($lease->fresh()->released_at);
        }

        $lease->fresh()->forceFill(['expires_at' => now()->addMinute(), 'owner_job_id' => $first->id])->save();
        $refreshed = $manager->acquire('resource:panel', $first->id, 2, 30);
        $this->assertSame($lease->id, $refreshed->id);
        $this->assertSame(2, $refreshed->attempt);

        $manager->release($refreshed);
        $replacement = $manager->acquire('resource:panel', $second->id, 1, 30);
        $this->assertNotSame($lease->id, $replacement->id);

        $this->expectException(LeaseConflict::class);
        try {
            ExecutionLease::query()->create([
                'resource_key' => 'resource:panel',
                'owner_job_id' => $first->id,
                'attempt' => 1,
                'fencing_token' => 9,
                'expires_at' => now()->addMinute(),
            ]);
            $this->fail('Two active leases were stored.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->errorInfo[0] ?? null);
            throw new LeaseConflict('unique index');
        }
    }

    public function test_partial_indexes_exist(): void
    {
        $indexes = collect(DB::select('select indexname, indexdef from pg_indexes where schemaname = current_schema()'))
            ->keyBy('indexname');

        $this->assertStringContainsString('released_at is null', strtolower((string) $indexes['execution_leases_one_active_idx']->indexdef));
        $this->assertStringContainsString('published_at is null', strtolower((string) $indexes['operation_job_outbox_unpublished_idx']->indexdef));
        $this->assertStringContainsString('where', strtolower((string) $indexes['operation_jobs_active_idx']->indexdef));
    }

    private function operation(string $workspaceId, string $actorId, string $key, array $plan): RecordOperationData
    {
        return new RecordOperationData(
            workspaceId: $workspaceId,
            actorId: $actorId,
            type: 'host.noop',
            idempotencyKey: $key,
            plan: $plan,
            mutatesHost: false,
        );
    }
}
