<?php

declare(strict_types=1);

namespace App\Domain\Git\Enums;

enum RepositoryVisibility: string
{
    case Public = 'public';
    case Private = 'private';
    case Internal = 'internal';
}
