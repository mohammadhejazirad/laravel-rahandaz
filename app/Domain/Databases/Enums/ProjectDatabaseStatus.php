<?php

declare(strict_types=1);

namespace App\Domain\Databases\Enums;

enum ProjectDatabaseStatus: string
{
    case Requested = 'requested';
    case Ready = 'ready';
    case Disabled = 'disabled';
    case Dropped = 'dropped';
}
