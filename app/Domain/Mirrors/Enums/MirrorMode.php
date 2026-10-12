<?php

declare(strict_types=1);

namespace App\Domain\Mirrors\Enums;

enum MirrorMode: string
{
    case Official = 'official';
    case DomesticAuto = 'domestic_auto';
    case DomesticManual = 'domestic_manual';
    case Custom = 'custom';
}
