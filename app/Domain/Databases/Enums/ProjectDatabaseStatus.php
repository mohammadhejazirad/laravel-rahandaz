<?php

declare(strict_types=1);

namespace App\Domain\Databases\Enums;

use App\Support\HasEnumLabel;

enum ProjectDatabaseStatus: string
{
    use HasEnumLabel;

    case Requested = 'requested';
    case Ready = 'ready';
    case Disabled = 'disabled';
    case Dropped = 'dropped';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'درخواست‌شده',
            self::Ready => 'آماده',
            self::Disabled => 'غیرفعال',
            self::Dropped => 'حذف‌شده',
        };
    }
}
