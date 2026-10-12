<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

use App\Support\HasEnumLabel;

enum ServiceKind: string
{
    use HasEnumLabel;

    case Web = 'web';
    case Runtime = 'runtime';
    case Database = 'database';
    case Cache = 'cache';
    case Tool = 'tool';
    case Security = 'security';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'وب',
            self::Runtime => 'زمان اجرا',
            self::Database => 'دیتابیس',
            self::Cache => 'کش',
            self::Tool => 'ابزار',
            self::Security => 'امنیت',
            self::Other => 'سایر',
        };
    }
}
