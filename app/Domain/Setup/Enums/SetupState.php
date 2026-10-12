<?php

declare(strict_types=1);

namespace App\Domain\Setup\Enums;

use App\Support\HasEnumLabel;

enum SetupState: string
{
    use HasEnumLabel;

    case InProgress = 'in_progress';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Blocked = 'blocked';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'در حال انجام',
            self::Completed => 'تکمیل‌شده',
            self::CompletedWithErrors => 'تکمیل با خطا',
            self::Blocked => 'مسدود',
            self::Cancelled => 'لغوشده',
        };
    }
}
