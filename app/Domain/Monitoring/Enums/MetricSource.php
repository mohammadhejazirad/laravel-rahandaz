<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Enums;

use App\Support\HasEnumLabel;

enum MetricSource: string
{
    use HasEnumLabel;

    case Host = 'host';
    case Service = 'service';
    case Queue = 'queue';
    case Deployment = 'deployment';

    public function label(): string
    {
        return match ($this) {
            self::Host => 'میزبان',
            self::Service => 'سرویس',
            self::Queue => 'صف',
            self::Deployment => 'استقرار',
        };
    }
}
