<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum ServiceHealthStatus: string
{
    case Unknown = 'unknown';
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unhealthy = 'unhealthy';
}
