<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

use App\Support\HasEnumLabel;

enum DeploymentStatus: string
{
    use HasEnumLabel;

    case Queued = 'queued';
    case Running = 'running';
    case Verifying = 'verifying';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case RollingBack = 'rolling_back';
    case RolledBack = 'rolled_back';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'در صف',
            self::Running => 'در حال اجرا',
            self::Verifying => 'در حال راستی‌آزمایی',
            self::Succeeded => 'موفق',
            self::Failed => 'ناموفق',
            self::Cancelled => 'لغوشده',
            self::RollingBack => 'در حال بازگشت',
            self::RolledBack => 'بازگشت‌داده‌شده',
        };
    }
}
