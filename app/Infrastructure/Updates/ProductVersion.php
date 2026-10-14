<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates;

final class ProductVersion
{
    public const VERSION = '1.0.0';

    public const CHANNEL = 'development';

    public const PHP = PhpRuntime::SERIES;
}
