<?php

declare(strict_types=1);

namespace App\Domain\Backups\Enums;

enum RestoreApproval: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
