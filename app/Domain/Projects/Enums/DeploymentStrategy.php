<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

use App\Support\HasEnumLabel;

enum DeploymentStrategy: string
{
    use HasEnumLabel;

    case SymlinkRelease = 'symlink_release';
    case BlueGreen = 'blue_green';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::SymlinkRelease => 'انتشار پیوندی',
            self::BlueGreen => 'آبی و سبز',
            self::Maintenance => 'نگهداری',
        };
    }
}
