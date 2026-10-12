<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

enum TlsMode: string
{
    case Off = 'off';
    case Custom = 'custom';
    case Acme = 'acme';
    case Assigned = 'assigned';
}
