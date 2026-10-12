<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

use App\Support\HasEnumLabel;

enum ProjectSourceType: string
{
    use HasEnumLabel;

    case Git = 'git';
    case Archive = 'archive';

    public function label(): string
    {
        return match ($this) {
            self::Git => 'Git',
            self::Archive => 'آرشیو ZIP',
        };
    }
}
