<?php

declare(strict_types=1);

namespace App\Domain\Git\Enums;

use App\Support\HasEnumLabel;

enum WebhookProcessingState: string
{
    use HasEnumLabel;

    case Received = 'received';
    case Rejected = 'rejected';
    case Queued = 'queued';
    case Processed = 'processed';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'دریافت‌شده',
            self::Rejected => 'ردشده',
            self::Queued => 'در صف',
            self::Processed => 'پردازش‌شده',
            self::Ignored => 'نادیده‌گرفته',
        };
    }
}
