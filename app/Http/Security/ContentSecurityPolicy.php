<?php

declare(strict_types=1);

namespace App\Http\Security;

final class ContentSecurityPolicy
{
    public static function production(?string $panelHost = null): string
    {
        $directives = [
            "default-src 'self'",
            "img-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self'",
            "font-src 'self'",
            "worker-src 'self'",
            "manifest-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
        ];
        if ($panelHost !== null && filter_var($panelHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // Cross-port polling stays on the explicitly enrolled panel IP.
            $directives[] = "connect-src 'self' http://".$panelHost.':*';
        }

        return implode('; ', $directives);
    }
}
