<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Enums;

use App\Support\HasEnumLabel;

enum JobStatus: string
{
    use HasEnumLabel;

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

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'در صف',
            self::Running => 'در حال اجرا',
            self::WaitingRetry => 'منتظر تلاش دوباره',
            self::Succeeded => 'موفق',
            self::Failed => 'ناموفق',
            self::Interrupted => 'قطع‌شده',
            self::Cancelling => 'در حال لغو',
            self::Cancelled => 'لغوشده',
            self::Reconciling => 'در حال تطبیق',
        };
    }
}
