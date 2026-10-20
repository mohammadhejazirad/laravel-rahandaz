<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;
use RuntimeException;

/**
 * Laravel's default key is only the user id, so every throttle limit shares one counter.
 */
class ThrottleByRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();
        $name = $route?->getName() ?: ($route?->uri() ?? $request->path());

        if ($user = $request->user()) {
            $identifier = $user->getAuthIdentifier().'|'.$name;
        } elseif ($route) {
            $identifier = ($route->getDomain() ?? '').'|'.$request->ip().'|'.$name;
        } else {
            throw new RuntimeException('Unable to generate the request signature. Route unavailable.');
        }

        return self::$shouldHashKeys ? sha1((string) $identifier) : (string) $identifier;
    }
}
