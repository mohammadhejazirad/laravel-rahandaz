<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

use App\Support\HasEnumLabel;

enum TlsMode: string
{
    use HasEnumLabel;

    case Off = 'off';
    case Custom = 'custom';
    case Acme = 'acme';
    case Assigned = 'assigned';

    public function label(): string
    {
        return match ($this) {
            self::Off => 'خاموش',
            self::Custom => 'سفارشی',
            self::Acme => 'ACME',
            self::Assigned => 'اختصاص‌یافته',
        };
    }
}
