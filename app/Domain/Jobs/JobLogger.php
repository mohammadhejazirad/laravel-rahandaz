<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Infrastructure\Secrets\Redactor;
use App\Models\OperationJob;
use App\Models\OperationJobLog;

final class JobLogger
{
    public function __construct(private readonly Redactor $redactor) {}

    public function append(OperationJob $job, string $level, string $message, ?string $stepKey = null): OperationJobLog
    {
        $last = OperationJobLog::query()
            ->where('operation_job_id', $job->id)
            ->orderByDesc('sequence')
            ->lockForUpdate()
            ->first();

        return OperationJobLog::query()->create([
            'operation_job_id' => $job->id,
            'sequence' => ($last->sequence ?? 0) + 1,
            'level' => $level,
            'step_key' => $stepKey,
            'message' => $this->redactor->redact($message),
            'recorded_at' => now(),
        ]);
    }
}
