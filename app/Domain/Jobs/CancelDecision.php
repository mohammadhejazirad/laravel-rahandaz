<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\Jobs\Enums\JobStatus;

final class CancelDecision
{
    /**
     * closed: already finished. finish: still queued, safe to mark cancelled.
     * flag: may already be in flight; record the request only.
     */
    public function mode(JobStatus $status): string
    {
        if ($status->isTerminal()) {
            return 'closed';
        }

        if ($status === JobStatus::Queued || $status === JobStatus::WaitingRetry) {
            return 'finish';
        }

        return 'flag';
    }
}
