<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\Jobs\Exceptions\LeaseConflict;
use App\Models\ExecutionLease;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class LeaseManager
{
    public function acquire(string $resourceKey, int|string $jobId, int $attempt, int $ttlSeconds = 60): ExecutionLease
    {
        $jobId = (string) $jobId;

        try {
            return DB::transaction(function () use ($resourceKey, $jobId, $attempt, $ttlSeconds): ExecutionLease {
                $active = ExecutionLease::query()
                    ->where('resource_key', $resourceKey)
                    ->whereNull('released_at')
                    ->lockForUpdate()
                    ->first();

                if ($active !== null && (string) $active->owner_job_id === $jobId && $active->expires_at->isFuture()) {
                    $active->forceFill([
                        'attempt' => $attempt,
                        'expires_at' => now()->addSeconds($ttlSeconds),
                    ])->save();

                    return $active;
                }

                if ($active !== null) {
                    $reason = $active->expires_at->isPast()
                        ? 'Expired lease requires reconciliation before it can be replaced.'
                        : 'Resource is already leased.';

                    throw new LeaseConflict($reason);
                }

                $token = (int) ExecutionLease::query()
                    ->where('resource_key', $resourceKey)
                    ->max('fencing_token') + 1;

                return ExecutionLease::query()->create([
                    'resource_key' => $resourceKey,
                    'owner_job_id' => $jobId,
                    'attempt' => $attempt,
                    'fencing_token' => $token,
                    'expires_at' => now()->addSeconds($ttlSeconds),
                ]);
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw new LeaseConflict('Resource is already leased.');
            }

            throw $exception;
        }
    }

    public function release(ExecutionLease $lease): void
    {
        if ($lease->released_at !== null) {
            return;
        }

        $lease->forceFill(['released_at' => now()])->save();
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? '';

        return $sqlState === '23505';
    }
}
