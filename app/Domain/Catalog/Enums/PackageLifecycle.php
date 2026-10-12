<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

use App\Support\HasEnumLabel;

enum PackageLifecycle: string
{
    use HasEnumLabel;

    case Active = 'active';
    case Untested = 'untested';
    case Disabled = 'disabled';
    case Eol = 'eol';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Untested => 'آزموده‌نشده',
            self::Disabled => 'غیرفعال',
            self::Eol => 'پایان عمر',
        };
    }
}
