<?php

declare(strict_types=1);

namespace App\Domain\Mirrors\Enums;

enum TrustStatus: string
{
    case Trusted = 'trusted';
    case Candidate = 'candidate';
    case Disabled = 'disabled';
}
