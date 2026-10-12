<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum ServiceManagement: string
{
    case Unmanaged = 'unmanaged';
    case Adopted = 'adopted';
    case PanelInstalled = 'panel_installed';
}
