<?php

declare(strict_types=1);

namespace App\Domain\Setup\Enums;

use App\Support\HasEnumLabel;

enum SetupComponentState: string
{
    use HasEnumLabel;

    case NotStarted = 'not_started';
    case Ready = 'ready';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'شروع‌نشده',
            self::Ready => 'آماده',
            self::Running => 'در حال اجرا',
            self::Succeeded => 'موفق',
            self::Failed => 'ناموفق',
            self::Skipped => 'عبورکرده',
            self::Blocked => 'مسدود',
        };
    }
}
