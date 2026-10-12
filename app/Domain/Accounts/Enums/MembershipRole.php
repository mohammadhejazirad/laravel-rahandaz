<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Enums;

use App\Support\HasEnumLabel;

enum MembershipRole: string
{
    use HasEnumLabel;

    case Owner = 'owner';
    case Admin = 'admin';
    case Operator = 'operator';
    case Viewer = 'viewer';

    public function managesHost(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    public function mayDecryptSecrets(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'مالک',
            self::Admin => 'مدیر',
            self::Operator => 'اپراتور',
            self::Viewer => 'مشاهده‌گر',
        };
    }
}
