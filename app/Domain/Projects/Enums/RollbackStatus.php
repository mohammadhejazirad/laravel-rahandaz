<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

enum RollbackStatus: string
{
    case None = 'none';
    case Requested = 'requested';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
