<?php

declare(strict_types=1);

namespace App\Domain\Setup\Enums;

enum SetupComponentState: string
{
    case NotStarted = 'not_started';
    case Ready = 'ready';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Blocked = 'blocked';
}
