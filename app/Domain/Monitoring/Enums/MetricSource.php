<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Enums;

enum MetricSource: string
{
    case Host = 'host';
    case Service = 'service';
    case Queue = 'queue';
    case Deployment = 'deployment';
}
