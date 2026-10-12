<?php

declare(strict_types=1);

namespace App\Domain\Hosts\Enums;

use App\Support\HasEnumLabel;

enum FirewallState: string
{
    use HasEnumLabel;

    case Desired = 'desired';
    case Observed = 'observed';
    case Applied = 'applied';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Desired => 'مطلوب',
            self::Observed => 'مشاهده‌شده',
            self::Applied => 'اعمال‌شده',
            self::Disabled => 'غیرفعال',
        };
    }
}
