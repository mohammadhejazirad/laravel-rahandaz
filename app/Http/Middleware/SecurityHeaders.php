<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Security\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->header('X-Inertia') || str_starts_with((string) $response->headers->get('Content-Type'), 'text/html') || $request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (app()->environment('production')) {
            $response->headers->set('Content-Security-Policy', ContentSecurityPolicy::production());
        }

        return $response;
    }
}
