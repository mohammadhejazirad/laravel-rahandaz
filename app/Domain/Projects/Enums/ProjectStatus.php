<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

use App\Support\HasEnumLabel;

enum ProjectStatus: string
{
    use HasEnumLabel;

    case Draft = 'draft';
    case Active = 'active';
    case Disabled = 'disabled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Active => 'فعال',
            self::Disabled => 'غیرفعال',
            self::Archived => 'بایگانی',
        };
    }
}
