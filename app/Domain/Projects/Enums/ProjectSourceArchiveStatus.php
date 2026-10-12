<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

use App\Support\HasEnumLabel;

enum ProjectSourceArchiveStatus: string
{
    use HasEnumLabel;

    case Ready = 'ready';
    case Expired = 'expired';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'آماده',
            self::Expired => 'منقضی',
            self::Deleted => 'حذف‌شده',
        };
    }
}
