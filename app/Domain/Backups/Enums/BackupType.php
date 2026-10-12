<?php

declare(strict_types=1);

namespace App\Domain\Backups\Enums;

use App\Support\HasEnumLabel;

enum BackupType: string
{
    use HasEnumLabel;

    case Database = 'database';
    case ControlPlane = 'control_plane';
    case Config = 'config';
    case Uploads = 'uploads';

    public function label(): string
    {
        return match ($this) {
            self::Database => 'دیتابیس',
            self::ControlPlane => 'صفحه کنترل',
            self::Config => 'پیکربندی',
            self::Uploads => 'فایل‌های بارگذاری‌شده',
        };
    }
}
