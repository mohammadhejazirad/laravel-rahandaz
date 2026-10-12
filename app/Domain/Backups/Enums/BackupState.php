<?php

declare(strict_types=1);

namespace App\Domain\Backups\Enums;

enum BackupState: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';
    case Pruned = 'pruned';
}
