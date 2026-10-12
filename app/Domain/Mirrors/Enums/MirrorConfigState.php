<?php

declare(strict_types=1);

namespace App\Domain\Mirrors\Enums;

enum MirrorConfigState: string
{
    case Desired = 'desired';
    case Applied = 'applied';
    case Degraded = 'degraded';
    case RolledBack = 'rolled_back';
}
