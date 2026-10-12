<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

use App\Support\HasEnumLabel;

enum PhaseStatus: string
{
    use HasEnumLabel;

    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار',
            self::Running => 'در حال اجرا',
            self::Succeeded => 'موفق',
            self::Failed => 'ناموفق',
            self::Skipped => 'عبورکرده',
        };
    }
}
