<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

use App\Support\HasEnumLabel;

enum EnvironmentStatus: string
{
    use HasEnumLabel;

    case Draft = 'draft';
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Degraded = 'degraded';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Provisioning => 'در حال تأمین',
            self::Active => 'فعال',
            self::Degraded => 'ناپایدار',
            self::Disabled => 'غیرفعال',
        };
    }
}
