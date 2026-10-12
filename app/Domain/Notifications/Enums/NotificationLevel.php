<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Enums;

use App\Support\HasEnumLabel;

enum NotificationLevel: string
{
    use HasEnumLabel;

    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'اطلاع',
            self::Success => 'موفق',
            self::Warning => 'هشدار',
            self::Danger => 'خطر',
        };
    }
}
