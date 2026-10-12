<?php

declare(strict_types=1);

namespace App\Domain\Hosts\Enums;

use App\Support\HasEnumLabel;

enum HostState: string
{
    use HasEnumLabel;

    case PendingProbe = 'pending_probe';
    case Compatible = 'compatible';
    case Degraded = 'degraded';
    case Unsupported = 'unsupported';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::PendingProbe => 'منتظر بررسی',
            self::Compatible => 'سازگار',
            self::Degraded => 'ناپایدار',
            self::Unsupported => 'پشتیبانی‌نشده',
            self::Disabled => 'غیرفعال',
        };
    }
}
