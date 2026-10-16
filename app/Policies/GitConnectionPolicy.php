<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\GrantPermission;
use App\Domain\Access\Enums\GrantResourceType;
use App\Models\GitConnection;
use App\Models\Membership;
use App\Models\ResourceGrant;
use App\Models\User;

final class GitConnectionPolicy
{
    public function view(User $user, GitConnection $connection): bool
    {
        $membership = Membership::query()->where('workspace_id', $connection->workspace_id)->where('user_id', $user->id)->first();

        return $membership !== null && ($membership->role->mayDecryptSecrets() || ResourceGrant::query()
            ->where('workspace_id', $connection->workspace_id)->where('membership_id', $membership->id)
            ->where('resource_type', GrantResourceType::GitConnection)->where('resource_id', $connection->id)
            ->whereIn('permission', [GrantPermission::View, GrantPermission::ManageSecrets])->exists());
    }

    public function update(User $user, GitConnection $connection): bool
    {
        // Provider credentials are restricted to owners/admins by CredentialStore.
        return Membership::query()->where('workspace_id', $connection->workspace_id)->where('user_id', $user->id)->first()?->role->mayDecryptSecrets() ?? false;
    }
}
