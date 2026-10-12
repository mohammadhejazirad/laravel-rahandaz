<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

use App\Support\HasEnumLabel;

enum RoutingKind: string
{
    use HasEnumLabel;

    case PublicHostname = 'public_hostname';
    case TemporaryInvalid = 'temporary_invalid';
    case TemporaryPath = 'temporary_path';

    public function label(): string
    {
        return match ($this) {
            self::PublicHostname => 'نام میزبان عمومی',
            self::TemporaryInvalid => 'موقت نامعتبر',
            self::TemporaryPath => 'مسیر موقت',
        };
    }
}
