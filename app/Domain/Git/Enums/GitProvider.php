<?php

declare(strict_types=1);

namespace App\Domain\Git\Enums;

enum GitProvider: string
{
    case Github = 'github';
    case Gitlab = 'gitlab';
}
