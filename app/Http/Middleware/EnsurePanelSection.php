<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\PanelSections;
use App\Domain\Accounts\Enums\MembershipRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePanelSection
{
    public function handle(Request $request, Closure $next): Response
    {
        $section = PanelSections::routeSection($request->route()?->getName());
        if ($section === null) {
            return $next($request);
        }
        $membership = $request->user()?->currentMembership();
        abort_if($membership === null, 403);
        $owner = $membership->role === MembershipRole::Owner;
        abort_unless(PanelSections::allows($membership->sections, $section, $owner), 403);

        return $next($request);
    }
}
