<?php

declare(strict_types=1);

namespace App\Domain\Git\Enums;

enum GitConnectionState: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Error = 'error';
}
