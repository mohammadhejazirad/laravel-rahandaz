<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Enums\StepStatus;
use App\Domain\Projects\Enums\DeploymentStatus;
use App\Infrastructure\Executor\ExecutionGate;
use App\Models\Deployment;
use App\Models\OperationJob;
use App\Models\RestoreOperation;
use Illuminate\Support\Facades\DB;

final class OperationRunner
{
    public function __construct(
        private readonly ExecutionGate $gate,
        private readonly JobLogger $logger,
        private readonly JobLifecycleRecorder $lifecycle,
    ) {}

    public function run(int|string $operationJobId): void
    {
        $operationJobId = (string) $operationJobId;

        DB::transaction(function () use ($operationJobId): void {
            $job = OperationJob::query()->lockForUpdate()->find($operationJobId);

            if ($job === null || $job->status->isTerminal()) {
                return;
            }

            if ($job->status !== JobStatus::Queued && $job->status !== JobStatus::WaitingRetry) {
                return;
            }

            $job->forceFill([
                'status' => JobStatus::Running,
                'attempt' => $job->attempt + 1,
                'started_at' => now(),
            ])->save();

            $this->lifecycle->record($job, 'started');

            $finishedAt = now();
            $job->forceFill([
                'status' => JobStatus::Failed,
                'error_code' => $this->gate->denialCode(),
                'finished_at' => $finishedAt,
            ])->save();

            $step = $job->steps()->where('key', 'gate')->first();

            if ($step !== null) {
                $step->forceFill([
                    'status' => StepStatus::Failed,
                    'attempt' => $step->attempt + 1,
                    'started_at' => $step->started_at ?? $finishedAt,
                    'finished_at' => $finishedAt,
                ])->save();
            }

            $this->logger->append($job, 'error', $this->gate->denialMessage(), 'gate');
            $this->lifecycle->record($job, 'failed');

            // Failure of the transport operation must release the domain mutation lock.
            Deployment::query()->where('operation_job_id', $job->id)->mutating()->update([
                'status' => DeploymentStatus::Failed->value,
                'failure_code' => $job->error_code,
                'finished_at' => $finishedAt,
            ]);
            RestoreOperation::query()->where('operation_job_id', $job->id)
                ->where('status', 'queued')->update(['status' => 'failed', 'failure_code' => $job->error_code, 'finished_at' => $finishedAt, 'updated_at' => $finishedAt]);
        });
    }
}
