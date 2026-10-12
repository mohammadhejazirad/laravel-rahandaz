<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
