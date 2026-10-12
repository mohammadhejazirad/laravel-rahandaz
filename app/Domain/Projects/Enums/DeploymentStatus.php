<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

enum DeploymentStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Verifying = 'verifying';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case RollingBack = 'rolling_back';
    case RolledBack = 'rolled_back';
}
