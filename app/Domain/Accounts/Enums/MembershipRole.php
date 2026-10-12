<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Enums;

enum MembershipRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Operator = 'operator';
    case Viewer = 'viewer';

    public function managesHost(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }
}
