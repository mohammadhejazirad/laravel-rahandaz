<?php

declare(strict_types=1);

namespace App\Domain\Hosts\Enums;

enum HostCompatibility: string
{
    case Untested = 'untested';
    case Supported = 'supported';
    case Unsupported = 'unsupported';
}
