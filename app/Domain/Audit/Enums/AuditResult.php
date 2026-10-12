<?php

declare(strict_types=1);

namespace App\Domain\Audit\Enums;

use App\Support\HasEnumLabel;

enum AuditResult: string
{
    use HasEnumLabel;

    case Success = 'success';
    case Failure = 'failure';
    case Denied = 'denied';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'موفق',
            self::Failure => 'شکست',
            self::Denied => 'ردشده',
        };
    }
}
