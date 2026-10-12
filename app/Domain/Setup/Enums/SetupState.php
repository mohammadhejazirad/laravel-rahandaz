<?php

declare(strict_types=1);

namespace App\Domain\Setup\Enums;

enum SetupState: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Blocked = 'blocked';
    case Cancelled = 'cancelled';
}
