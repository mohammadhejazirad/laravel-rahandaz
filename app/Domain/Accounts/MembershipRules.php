<?php

declare(strict_types=1);

namespace App\Domain\Accounts;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Accounts\Exceptions\LastOwnerException;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;

final class MembershipRules
{
    public function assertCanDemote(Workspace $workspace, User $target, MembershipRole $nextRole): void
    {
        $membership = Membership::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $target->id)
            ->first();

        if ($membership === null || $membership->role !== MembershipRole::Owner || $nextRole === MembershipRole::Owner) {
            return;
        }

        $owners = Membership::query()
            ->where('workspace_id', $workspace->id)
            ->where('role', MembershipRole::Owner)
            ->count();

        if ($owners <= 1) {
            throw new LastOwnerException;
        }
    }
}
