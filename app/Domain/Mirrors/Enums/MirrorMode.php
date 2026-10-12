<?php

declare(strict_types=1);

namespace App\Domain\Mirrors\Enums;

use App\Support\HasEnumLabel;

enum MirrorMode: string
{
    use HasEnumLabel;

    case Official = 'official';
    case DomesticAuto = 'domestic_auto';
    case DomesticManual = 'domestic_manual';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Official => 'رسمی',
            self::DomesticAuto => 'داخلی خودکار',
            self::DomesticManual => 'داخلی دستی',
            self::Custom => 'سفارشی',
        };
    }
}
