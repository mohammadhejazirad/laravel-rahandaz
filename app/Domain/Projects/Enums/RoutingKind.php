<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

enum RoutingKind: string
{
    case PublicHostname = 'public_hostname';
    case TemporaryInvalid = 'temporary_invalid';
    case TemporaryPath = 'temporary_path';
}
