<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum ServiceKind: string
{
    case Web = 'web';
    case Runtime = 'runtime';
    case Database = 'database';
    case Cache = 'cache';
    case Tool = 'tool';
    case Security = 'security';
    case Other = 'other';
}
