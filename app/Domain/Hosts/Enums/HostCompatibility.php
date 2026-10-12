<?php

declare(strict_types=1);

namespace App\Domain\Hosts\Enums;

use App\Support\HasEnumLabel;

enum HostCompatibility: string
{
    use HasEnumLabel;

    case Untested = 'untested';
    case Supported = 'supported';
    case Unsupported = 'unsupported';

    public function label(): string
    {
        return match ($this) {
            self::Untested => 'آزموده‌نشده',
            self::Supported => 'پشتیبانی‌شده',
            self::Unsupported => 'پشتیبانی‌نشده',
        };
    }
}
