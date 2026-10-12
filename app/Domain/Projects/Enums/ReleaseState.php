<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

enum ReleaseState: string
{
    case Building = 'building';
    case Ready = 'ready';
    case Active = 'active';
    case Previous = 'previous';
    case Failed = 'failed';
    case Retained = 'retained';
    case Pruned = 'pruned';
}
