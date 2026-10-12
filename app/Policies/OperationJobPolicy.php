<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\OperationJob;
use App\Models\User;
use App\Models\Workspace;

class OperationJobPolicy
{
    public function view(User $user, OperationJob $job): bool
    {
        return $this->role($user, $job->workspace_id) !== null;
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace->id)?->managesHost() === true;
    }

    private function role(User $user, int|string $workspaceId): ?MembershipRole
    {
        $role = Membership::query()
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $user->id)
            ->value('role');

        return $role instanceof MembershipRole ? $role : (is_string($role) ? MembershipRole::tryFrom($role) : null);
    }
}
