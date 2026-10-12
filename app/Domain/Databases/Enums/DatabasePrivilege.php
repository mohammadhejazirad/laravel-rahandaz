<?php

declare(strict_types=1);

namespace App\Domain\Databases\Enums;

enum DatabasePrivilege: string
{
    case Connect = 'connect';
    case Read = 'read';
    case ReadWrite = 'read_write';
    case Owner = 'owner';
}
