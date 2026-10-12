<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Enums;

use App\Support\HasEnumLabel;

enum UserStatus: string
{
    use HasEnumLabel;

    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Suspended => 'معلق',
        };
    }
}
