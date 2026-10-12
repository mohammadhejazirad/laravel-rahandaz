<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Enums;

use App\Support\HasEnumLabel;

enum StepStatus: string
{
    use HasEnumLabel;

    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'در صف',
            self::Running => 'در حال اجرا',
            self::Succeeded => 'موفق',
            self::Failed => 'ناموفق',
            self::Skipped => 'عبورکرده',
        };
    }
}
