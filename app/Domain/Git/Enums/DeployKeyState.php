<?php

declare(strict_types=1);

namespace App\Domain\Git\Enums;

enum DeployKeyState: string
{
    case Pending = 'pending';
    case Registered = 'registered';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Revoked = 'revoked';
}
