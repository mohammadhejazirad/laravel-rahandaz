<?php

declare(strict_types=1);

namespace App\Domain\Git\Enums;

use App\Support\HasEnumLabel;

enum RepositoryVisibility: string
{
    use HasEnumLabel;

    case Public = 'public';
    case Private = 'private';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'عمومی',
            self::Private => 'خصوصی',
            self::Internal => 'داخلی',
        };
    }
}
