<?php

declare(strict_types=1);

namespace App\Domain\Backups\Enums;

use App\Support\HasEnumLabel;

enum BackupState: string
{
    use HasEnumLabel;

    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';
    case Pruned = 'pruned';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'در حال اجرا',
            self::Succeeded => 'موفق',
            self::Failed => 'ناموفق',
            self::Expired => 'منقضی',
            self::Pruned => 'پاک‌شده',
        };
    }
}
