<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return Membership::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
