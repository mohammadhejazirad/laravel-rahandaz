<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\Uid\Uuid;

final class Identifiers
{
    public static function uuid7(): string
    {
        return strtolower((string) Str::uuid7());
    }

    public static function isUuidV7(string $value): bool
    {
        $value = strtolower($value);

        if (! Uuid::isValid($value)) {
            return false;
        }

        return strlen($value) === 36 && $value[14] === '7';
    }
}
