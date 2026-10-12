<?php

declare(strict_types=1);

namespace App\Support;

trait HasEnumLabel
{
    abstract public function label(): string;

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }

    public static function labelFor(self|string|null $value): string
    {
        if ($value instanceof self) {
            return $value->label();
        }

        if (! is_string($value) || $value === '') {
            return '';
        }

        return self::tryFrom($value)?->label() ?? $value;
    }
}
