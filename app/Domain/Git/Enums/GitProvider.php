<?php

declare(strict_types=1);

namespace App\Domain\Git\Enums;

use App\Support\HasEnumLabel;

enum GitProvider: string
{
    use HasEnumLabel;

    case Github = 'github';
    case Gitlab = 'gitlab';

    public function label(): string
    {
        return match ($this) {
            self::Github => 'GitHub',
            self::Gitlab => 'GitLab',
        };
    }
}
