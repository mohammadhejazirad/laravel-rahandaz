<?php

declare(strict_types=1);

namespace App\Domain\Mirrors\Enums;

use App\Support\HasEnumLabel;

enum MirrorConfigState: string
{
    use HasEnumLabel;

    case Desired = 'desired';
    case Applied = 'applied';
    case Degraded = 'degraded';
    case RolledBack = 'rolled_back';

    public function label(): string
    {
        return match ($this) {
            self::Desired => 'مطلوب',
            self::Applied => 'اعمال‌شده',
            self::Degraded => 'ناپایدار',
            self::RolledBack => 'بازگشت‌داده‌شده',
        };
    }
}
