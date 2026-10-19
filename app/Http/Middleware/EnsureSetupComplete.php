<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->user()?->currentWorkspace();

        if ($workspace !== null && ! $workspace->setupIsComplete() && ! $request->routeIs('setup.show', 'logout', 'settings.profile', 'settings.panel-port', 'settings.panel-port.status')) {
            return redirect()->route('setup.show');
        }

        return $next($request);
    }
}
