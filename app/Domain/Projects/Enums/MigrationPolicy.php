<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

use App\Support\HasEnumLabel;

enum MigrationPolicy: string
{
    use HasEnumLabel;

    case None = 'none';
    case ExpandOnly = 'expand_only';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::None => 'ندارد',
            self::ExpandOnly => 'فقط گسترش',
            self::Maintenance => 'نگهداری',
        };
    }
}
