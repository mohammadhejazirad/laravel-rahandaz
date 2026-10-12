<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Enums;

enum JobStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case WaitingRetry = 'waiting_retry';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Interrupted = 'interrupted';
    case Cancelling = 'cancelling';
    case Cancelled = 'cancelled';
    case Reconciling = 'reconciling';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded, self::Failed, self::Cancelled => true,
            default => false,
        };
    }
}
