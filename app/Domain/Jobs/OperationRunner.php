<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\Access\OperationAccess;
use App\Domain\Catalog\RunSoftware;
use App\Domain\Catalog\SoftwareCapability;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Enums\StepStatus;
use App\Domain\Projects\Enums\DeploymentStatus;
use App\Domain\Settings\RunPanelPort;
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
        private readonly OperationAccess $access,
    ) {}

    public function run(int|string $operationJobId): void
    {
        $operationJobId = (string) $operationJobId;

        if (OperationJob::query()->whereKey($operationJobId)->whereIn('type', SoftwareCapability::JOB_TYPES)->exists()) {
            app(RunSoftware::class)->run($operationJobId);

            return;
        }

        if (OperationJob::query()->whereKey($operationJobId)->where('type', 'panel.port.update')->exists()) {
            app(RunPanelPort::class)->run($operationJobId);

            return;
        }

        DB::transaction(function () use ($operationJobId): void {
            $job = OperationJob::query()->lockForUpdate()->find($operationJobId);

            if ($job === null || $job->status->isTerminal()) {
                return;
            }

            if ($job->status !== JobStatus::Queued && $job->status !== JobStatus::WaitingRetry) {
                return;
            }

            if ($job->cancel_requested) {
                app(CancelOperation::class)->finishLocked($job);

                return;
            }

            $authorized = $this->access->mayExecute($job);
            $job->forceFill([
                'status' => JobStatus::Running,
                'attempt' => $job->attempt + 1,
                'started_at' => now(),
            ])->save();

            $this->lifecycle->record($job, 'started');

            $finishedAt = now();
            $job->forceFill([
                'status' => JobStatus::Failed,
                'error_code' => $authorized ? $this->gate->denialCode() : 'AUTHORIZATION_REVOKED',
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

            $this->logger->append($job, 'error', $authorized ? $this->gate->denialMessage() : 'مجوز اجرای درخواست دیگر معتبر نیست؛ هیچ فرمان میزبان اجرا نشد.', 'gate');
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
