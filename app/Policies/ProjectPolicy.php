<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return Membership::query()
            ->where('workspace_id', $project->workspace_id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function deploy(User $user, Project $project): bool
    {
        return Membership::query()
            ->where('workspace_id', $project->workspace_id)
            ->where('user_id', $user->id)
            ->whereIn('role', [MembershipRole::Owner, MembershipRole::Admin, MembershipRole::Operator])
            ->exists();
    }
}
