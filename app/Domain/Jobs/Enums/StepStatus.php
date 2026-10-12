<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Enums;

enum StepStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
