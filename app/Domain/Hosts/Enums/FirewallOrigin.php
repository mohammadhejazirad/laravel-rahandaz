<?php

declare(strict_types=1);

namespace App\Domain\Hosts\Enums;

use App\Support\HasEnumLabel;

enum FirewallOrigin: string
{
    use HasEnumLabel;

    case SystemDiscovered = 'system_discovered';
    case PanelManaged = 'panel_managed';

    public function label(): string
    {
        return match ($this) {
            self::SystemDiscovered => 'کشف سیستمی',
            self::PanelManaged => 'مدیریت پنل',
        };
    }
}
