<?php

declare(strict_types=1);

namespace App\Http\Security;

final class ContentSecurityPolicy
{
    public static function production(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "img-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self'",
            "font-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
        ]);
    }
}
