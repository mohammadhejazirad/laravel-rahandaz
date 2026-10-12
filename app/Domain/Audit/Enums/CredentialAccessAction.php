<?php

declare(strict_types=1);

namespace App\Domain\Audit\Enums;

use App\Support\HasEnumLabel;

enum CredentialAccessAction: string
{
    use HasEnumLabel;

    case Store = 'store';
    case Decrypt = 'decrypt';
    case Rotate = 'rotate';
    case Revoke = 'revoke';

    public function label(): string
    {
        return match ($this) {
            self::Store => 'ذخیره',
            self::Decrypt => 'رمزگشایی',
            self::Rotate => 'چرخش',
            self::Revoke => 'ابطال',
        };
    }
}
