<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Enums;

use App\Support\HasEnumLabel;

enum Theme: string
{
    use HasEnumLabel;

    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Light => 'روشن',
            self::Dark => 'تیره',
            self::System => 'سیستم',
        };
    }
}
