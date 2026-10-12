<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums;

use App\Support\HasEnumLabel;

enum GrantResourceType: string
{
    use HasEnumLabel;

    case Project = 'project';
    case Environment = 'environment';
    case ManagedService = 'managed_service';
    case DatabaseInstance = 'database_instance';
    case GitConnection = 'git_connection';
    case LocalHost = 'local_host';

    public function label(): string
    {
        return match ($this) {
            self::Project => 'پروژه',
            self::Environment => 'محیط',
            self::ManagedService => 'سرویس مدیریت‌شده',
            self::DatabaseInstance => 'نمونه دیتابیس',
            self::GitConnection => 'اتصال گیت',
            self::LocalHost => 'میزبان محلی',
        };
    }
}
