<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

enum CertificateInstallState: string
{
    case Requested = 'requested';
    case Pending = 'pending';
    case Installed = 'installed';
    case Failed = 'failed';
    case Expired = 'expired';
}
