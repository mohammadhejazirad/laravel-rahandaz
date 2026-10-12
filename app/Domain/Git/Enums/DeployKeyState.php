<?php

declare(strict_types=1);

namespace App\Domain\Git\Enums;

use App\Support\HasEnumLabel;

enum DeployKeyState: string
{
    use HasEnumLabel;

    case Pending = 'pending';
    case Registered = 'registered';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار',
            self::Registered => 'ثبت‌شده',
            self::Verified => 'تأییدشده',
            self::Rejected => 'ردشده',
            self::Revoked => 'باطل‌شده',
        };
    }
}
