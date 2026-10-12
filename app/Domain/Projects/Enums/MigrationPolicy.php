<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

enum MigrationPolicy: string
{
    case None = 'none';
    case ExpandOnly = 'expand_only';
    case Maintenance = 'maintenance';
}
