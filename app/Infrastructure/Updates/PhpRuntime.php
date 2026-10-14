<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates;

final class PhpRuntime
{
    public const SERIES = '8.4';

    public static function isRequiredSeries(?string $version = null): bool
    {
        $version ??= PHP_VERSION;

        return preg_match('/^8\.4(\.|$)/', $version) === 1;
    }
}
