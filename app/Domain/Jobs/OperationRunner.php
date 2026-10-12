<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Enums\StepStatus;
use App\Infrastructure\Executor\ExecutionGate;
use App\Models\OperationJob;
use Illuminate\Support\Facades\DB;

final class OperationRunner
{
    public function __construct(
        private readonly ExecutionGate $gate,
        private readonly JobLogger $logger,
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
        });
    }
}
