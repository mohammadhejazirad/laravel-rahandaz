<?php

declare(strict_types=1);

namespace App\Domain\Settings\Enums;

enum SettingScope: string
{
    case Installation = 'installation';
    case Workspace = 'workspace';
}
