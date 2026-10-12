<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum PackageLifecycle: string
{
    case Active = 'active';
    case Untested = 'untested';
    case Disabled = 'disabled';
    case Eol = 'eol';
}
