<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Infrastructure\Host\ReadOnlyHostProbe;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function show(ReadOnlyHostProbe $probe): Response
    {
        $user = request()->user();
        abort_if($user === null, 403);

        $workspace = $user->currentWorkspace();
        abort_if($workspace === null, 403);
        $this->authorize('view', $workspace);

        $isOwner = $user->role() === MembershipRole::Owner;

        return Inertia::render('setup/index', [
            'holding' => ! $isOwner,
            'workspace' => [
                'uuid' => $workspace->uuid,
                'name' => $workspace->name,
                'setup_completed_at' => $workspace->setup_completed_at?->toIso8601String(),
            ],
            'facts' => $isOwner ? $probe->collect() : null,
        ]);
    }
}
