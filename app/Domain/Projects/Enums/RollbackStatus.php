<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

use App\Support\HasEnumLabel;

enum RollbackStatus: string
{
    use HasEnumLabel;

    case None = 'none';
    case Requested = 'requested';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::None => 'ندارد',
            self::Requested => 'درخواست‌شده',
            self::Running => 'در حال اجرا',
            self::Succeeded => 'موفق',
            self::Failed => 'ناموفق',
        };
    }
}
