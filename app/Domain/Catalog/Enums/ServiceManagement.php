<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

use App\Support\HasEnumLabel;

enum ServiceManagement: string
{
    use HasEnumLabel;

    case Unmanaged = 'unmanaged';
    case Adopted = 'adopted';
    case PanelInstalled = 'panel_installed';

    public function label(): string
    {
        return match ($this) {
            self::Unmanaged => 'مدیریت‌نشده',
            self::Adopted => 'پذیرش‌شده',
            self::PanelInstalled => 'نصب‌شده توسط پنل',
        };
    }
}
