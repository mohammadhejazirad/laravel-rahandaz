<?php

declare(strict_types=1);

namespace App\Domain\Hosts\Enums;

enum FirewallOrigin: string
{
    case SystemDiscovered = 'system_discovered';
    case PanelManaged = 'panel_managed';
}
