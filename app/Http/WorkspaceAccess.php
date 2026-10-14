<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;

final class WorkspaceAccess
{
    public static function membership(User $user, Workspace $workspace): Membership
    {
        $membership = Membership::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();

        abort_if($membership === null, 403);

        return $membership;
    }

    public static function assertOperates(User $user, Workspace $workspace): void
    {
        $role = self::membership($user, $workspace)->role;

        abort_unless(in_array($role, [MembershipRole::Owner, MembershipRole::Admin, MembershipRole::Operator], true), 403);
    }

    public static function assertManagesSecrets(User $user, Workspace $workspace): void
    {
        abort_unless(self::membership($user, $workspace)->role->mayDecryptSecrets(), 403);
    }
}
