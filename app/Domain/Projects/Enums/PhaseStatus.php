<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

enum PhaseStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
