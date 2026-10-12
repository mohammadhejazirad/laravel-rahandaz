<?php

declare(strict_types=1);

namespace App\Domain\Git\Enums;

use App\Support\HasEnumLabel;

enum GitConnectionState: string
{
    use HasEnumLabel;

    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Expired => 'منقضی',
            self::Revoked => 'باطل‌شده',
            self::Error => 'خطا',
        };
    }
}
