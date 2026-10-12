<?php

declare(strict_types=1);

namespace App\Domain\Settings\Enums;

use App\Support\HasEnumLabel;

enum SettingScope: string
{
    use HasEnumLabel;

    case Installation = 'installation';
    case Workspace = 'workspace';

    public function label(): string
    {
        return match ($this) {
            self::Installation => 'نصب',
            self::Workspace => 'فضای کاری',
        };
    }
}
