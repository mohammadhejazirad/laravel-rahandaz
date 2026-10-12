<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums;

use App\Support\HasEnumLabel;

enum GrantPermission: string
{
    use HasEnumLabel;

    case View = 'view';
    case Deploy = 'deploy';
    case Operate = 'operate';
    case ManageSecrets = 'manage_secrets';
    case Restore = 'restore';

    public function label(): string
    {
        return match ($this) {
            self::View => 'مشاهده',
            self::Deploy => 'استقرار',
            self::Operate => 'عملیات',
            self::ManageSecrets => 'مدیریت رازها',
            self::Restore => 'بازیابی',
        };
    }
}
