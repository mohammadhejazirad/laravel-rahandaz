<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

enum EnvironmentStatus: string
{
    case Draft = 'draft';
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Degraded = 'degraded';
    case Disabled = 'disabled';
}
