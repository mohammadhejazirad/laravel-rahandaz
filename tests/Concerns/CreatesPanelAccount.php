<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Workspace;

trait CreatesPanelAccount
{
    /**
     * @param  array<string, mixed>  $userAttributes
     * @param  array<string, mixed>  $workspaceAttributes
     * @return array{0: User, 1: Workspace}
     */
    protected function panelAccount(
        MembershipRole $role = MembershipRole::Owner,
        array $userAttributes = [],
        array $workspaceAttributes = [],
        bool $forcePasswordChange = false,
        bool $setupComplete = false,
    ): array {
        $user = User::factory()->create($userAttributes);
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'force_password_change' => $forcePasswordChange,
        ]);
        $workspace = Workspace::factory()->create([
            'owner_id' => $user->id,
            'setup_completed_at' => $setupComplete ? now() : null,
            ...$workspaceAttributes,
        ]);
        Membership::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return [$user->fresh() ?? $user, $workspace->fresh() ?? $workspace];
    }
}
