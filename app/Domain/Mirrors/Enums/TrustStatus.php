<?php

declare(strict_types=1);

namespace App\Domain\Mirrors\Enums;

use App\Support\HasEnumLabel;

enum TrustStatus: string
{
    use HasEnumLabel;

    case Trusted = 'trusted';
    case Candidate = 'candidate';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Trusted => 'مورد اعتماد',
            self::Candidate => 'نامزد',
            self::Disabled => 'غیرفعال',
        };
    }
}
