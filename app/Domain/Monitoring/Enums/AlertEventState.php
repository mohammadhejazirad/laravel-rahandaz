<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Enums;

use App\Support\HasEnumLabel;

enum AlertEventState: string
{
    use HasEnumLabel;

    case Firing = 'firing';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Firing => 'در حال هشدار',
            self::Resolved => 'برطرف‌شده',
        };
    }
}
