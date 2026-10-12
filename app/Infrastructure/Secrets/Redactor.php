<?php

declare(strict_types=1);

namespace App\Infrastructure\Secrets;

final class Redactor
{
    public function redact(string $text): string
    {
        $text = preg_replace('#https?://[^/\s:]+:[^/\s@]+@#i', 'https://redacted@', $text) ?? $text;
        $text = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $text) ?? $text;
        $text = preg_replace('/(?i)(password|secret|token|api_key)\s*[=:]\s*\S+/', '$1=[redacted]', $text) ?? $text;

        return $text;
    }
}
