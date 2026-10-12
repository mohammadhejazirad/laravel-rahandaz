<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

enum DeploymentStrategy: string
{
    case SymlinkRelease = 'symlink_release';
    case BlueGreen = 'blue_green';
    case Maintenance = 'maintenance';
}
