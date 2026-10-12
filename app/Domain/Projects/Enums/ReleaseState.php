<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

use App\Support\HasEnumLabel;

enum ReleaseState: string
{
    use HasEnumLabel;

    case Building = 'building';
    case Ready = 'ready';
    case Active = 'active';
    case Previous = 'previous';
    case Failed = 'failed';
    case Retained = 'retained';
    case Pruned = 'pruned';

    public function label(): string
    {
        return match ($this) {
            self::Building => 'در حال ساخت',
            self::Ready => 'آماده',
            self::Active => 'فعال',
            self::Previous => 'قبلی',
            self::Failed => 'ناموفق',
            self::Retained => 'نگه‌داشته‌شده',
            self::Pruned => 'پاک‌شده',
        };
    }
}
