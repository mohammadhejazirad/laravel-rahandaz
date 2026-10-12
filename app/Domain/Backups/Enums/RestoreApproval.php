<?php

declare(strict_types=1);

namespace App\Domain\Backups\Enums;

use App\Support\HasEnumLabel;

enum RestoreApproval: string
{
    use HasEnumLabel;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار',
            self::Approved => 'تأییدشده',
            self::Rejected => 'ردشده',
        };
    }
}
