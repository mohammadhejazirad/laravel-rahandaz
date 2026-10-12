<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum ServiceRuntimeState: string
{
    case Unknown = 'unknown';
    case Running = 'running';
    case Stopped = 'stopped';
    case Failed = 'failed';
    case Inactive = 'inactive';
}
