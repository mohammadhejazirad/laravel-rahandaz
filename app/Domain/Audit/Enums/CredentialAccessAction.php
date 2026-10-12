<?php

declare(strict_types=1);

namespace App\Domain\Audit\Enums;

enum CredentialAccessAction: string
{
    case Store = 'store';
    case Decrypt = 'decrypt';
    case Rotate = 'rotate';
    case Revoke = 'revoke';
}
