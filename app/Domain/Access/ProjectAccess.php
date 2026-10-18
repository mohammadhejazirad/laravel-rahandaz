<?php

declare(strict_types=1);

namespace App\Domain\Access;

use App\Domain\Access\Enums\GrantPermission;
use App\Domain\Access\Enums\GrantResourceType;
use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Accounts\Enums\UserStatus;
use App\Models\Membership;
use App\Models\Project;
use App\Models\ResourceGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ProjectAccess
{
    /** @return Builder<Project> */
    public function query(User $user, int|string $workspaceId, GrantPermission $permission = GrantPermission::View): Builder
    {
        $query = Project::query()->forWorkspace($workspaceId);
        $membership = Membership::query()->where('workspace_id', $workspaceId)->where('user_id', $user->id)->first();

        if ($user->status !== UserStatus::Active || $membership === null) {
            return $query->whereRaw('false');
        }

        if ($membership->role->managesHost()) {
            return $query;
        }

        if ($permission !== GrantPermission::View && ($permission !== GrantPermission::Deploy || $membership->role !== MembershipRole::Operator)) {
            return $query->whereRaw('false');
        }

        $permissions = $permission === GrantPermission::View
            ? [GrantPermission::View, GrantPermission::Deploy, GrantPermission::Operate]
            : [$permission];

        return $query->whereIn('projects.id', ResourceGrant::query()
            ->where('workspace_id', $workspaceId)->where('membership_id', $membership->id)
            ->where('resource_type', GrantResourceType::Project)->whereIn('permission', $permissions)
            ->select('resource_id'));
    }
}
