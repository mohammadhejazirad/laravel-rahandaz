<?php

declare(strict_types=1);

namespace App\Domain\Hosts\Enums;

enum FirewallState: string
{
    case Desired = 'desired';
    case Observed = 'observed';
    case Applied = 'applied';
    case Disabled = 'disabled';
}
