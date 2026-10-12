<?php

declare(strict_types=1);

namespace App\Domain\Databases\Enums;

use App\Support\HasEnumLabel;

enum DatabasePrivilege: string
{
    use HasEnumLabel;

    case Connect = 'connect';
    case Read = 'read';
    case ReadWrite = 'read_write';
    case Owner = 'owner';

    public function label(): string
    {
        return match ($this) {
            self::Connect => 'اتصال',
            self::Read => 'خواندن',
            self::ReadWrite => 'خواندن و نوشتن',
            self::Owner => 'مالک',
        };
    }
}
