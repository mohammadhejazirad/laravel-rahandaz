<?php

declare(strict_types=1);

namespace App\Domain\Hosts\Enums;

enum HostState: string
{
    case PendingProbe = 'pending_probe';
    case Compatible = 'compatible';
    case Degraded = 'degraded';
    case Unsupported = 'unsupported';
    case Disabled = 'disabled';
}
