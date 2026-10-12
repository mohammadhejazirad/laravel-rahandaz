<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Jobs\HandleOperationJob;
use App\Models\OperationJobOutbox;
use Illuminate\Support\Facades\DB;

final class OutboxDispatcher
{
    public function publishPending(int $limit = 50): int
    {
        $published = 0;

        DB::transaction(function () use (&$published, $limit): void {
            $rows = OperationJobOutbox::query()
                ->whereNull('published_at')
                ->where('available_at', '<=', now())
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit($limit)
                ->lock('for update skip locked')
                ->get();

            foreach ($rows as $row) {
                $jobId = (string) ($row->payload['operation_job_id'] ?? $row->operation_job_id);

                HandleOperationJob::dispatch($jobId)
                    ->onConnection((string) config('queue.default'))
                    ->onQueue('control');

                $row->forceFill([
                    'published_at' => now(),
                    'attempt' => $row->attempt + 1,
                ])->save();

                $published++;
            }
        });

        return $published;
    }
}
