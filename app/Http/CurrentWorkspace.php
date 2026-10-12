<?php

declare(strict_types=1);

namespace App\Http;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class CurrentWorkspace
{
    public static function get(Request $request): Workspace
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $workspace = $user->currentWorkspace();
        abort_if($workspace === null, 403);
        Gate::authorize('view', $workspace);

        return $workspace;
    }
}
