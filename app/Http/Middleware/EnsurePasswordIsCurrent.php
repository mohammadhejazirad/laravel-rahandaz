<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $user->loadMissing('profile');
        }

        if ($user?->profile?->force_password_change && ! $request->routeIs('password.edit', 'password.update', 'logout')) {
            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
