<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

use App\Support\HasEnumLabel;

enum ServiceHealthStatus: string
{
    use HasEnumLabel;

    case Unknown = 'unknown';
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unhealthy = 'unhealthy';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'نامشخص',
            self::Healthy => 'سالم',
            self::Degraded => 'ناپایدار',
            self::Unhealthy => 'ناسالم',
        };
    }
}
