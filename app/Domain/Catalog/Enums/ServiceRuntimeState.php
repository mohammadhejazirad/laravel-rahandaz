<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

use App\Support\HasEnumLabel;

enum ServiceRuntimeState: string
{
    use HasEnumLabel;

    case Unknown = 'unknown';
    case Running = 'running';
    case Stopped = 'stopped';
    case Failed = 'failed';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'نامشخص',
            self::Running => 'در حال اجرا',
            self::Stopped => 'متوقف',
            self::Failed => 'ناموفق',
            self::Inactive => 'نافعال',
        };
    }
}
