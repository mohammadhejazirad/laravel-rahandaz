<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Enums;

enum AlertEventState: string
{
    case Firing = 'firing';
    case Resolved = 'resolved';
}
