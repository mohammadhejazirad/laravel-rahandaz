<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

use App\Support\HasEnumLabel;

enum CertificateInstallState: string
{
    use HasEnumLabel;

    case Requested = 'requested';
    case Pending = 'pending';
    case Installed = 'installed';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'درخواست‌شده',
            self::Pending => 'در انتظار',
            self::Installed => 'نصب‌شده',
            self::Failed => 'ناموفق',
            self::Expired => 'منقضی',
        };
    }
}
