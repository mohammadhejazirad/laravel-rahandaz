<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Enums\StepStatus;
use App\Domain\Jobs\Exceptions\IdempotencyConflict;
use App\Models\OperationJob;
use Illuminate\Support\Facades\DB;

final class RecordOperation
{
    public function __construct(private readonly PlanGuard $plans) {}

    public function record(RecordOperationData $data): OperationJob
    {
        $this->plans->assertSafe($data->plan);
        $hash = $this->plans->hash($data);

        return DB::transaction(function () use ($data, $hash): OperationJob {
            $existing = OperationJob::query()
                ->where('workspace_id', $data->workspaceId)
                ->where('actor_id', $data->actorId)
                ->where('type', $data->type)
                ->where('idempotency_key', $data->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    throw new IdempotencyConflict;
                }

                return $existing;
            }

            $job = OperationJob::query()->create([
                'workspace_id' => $data->workspaceId,
                'actor_id' => $data->actorId,
                'type' => $data->type,
                'status' => JobStatus::Queued,
                'idempotency_key' => $data->idempotencyKey,
                'request_hash' => $hash,
                'plan' => [
                    'mutates_host' => $data->mutatesHost,
                    'input' => $data->plan,
                ],
                'attempt' => 0,
                'cancel_requested' => false,
            ]);

            $job->steps()->create([
                'key' => 'gate',
                'status' => StepStatus::Queued,
                'timeout_seconds' => (int) config('rahandaz.worker_timeout'),
                'attempt' => 0,
            ]);

            $job->outboxMessages()->create([
                'event_type' => 'job.queued',
                'payload' => ['operation_job_id' => (string) $job->id],
                'available_at' => now(),
                'attempt' => 0,
            ]);

            return $job;
        });
    }
}
