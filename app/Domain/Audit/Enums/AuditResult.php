<?php

declare(strict_types=1);

namespace App\Domain\Audit\Enums;

enum AuditResult: string
{
    case Success = 'success';
    case Failure = 'failure';
    case Denied = 'denied';
}
