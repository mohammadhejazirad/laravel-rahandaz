<?php

declare(strict_types=1);

namespace App\Domain\Access;

use App\Domain\Accounts\Enums\MembershipRole;

final class MembershipChange
{
    public function allowed(MembershipRole $actor, MembershipRole $target, bool $sameUser, bool $targetOwnsAnyWorkspace): bool
    {
        if ($sameUser || $target === MembershipRole::Owner || $targetOwnsAnyWorkspace) {
            return false;
        }

        return match ($actor) {
            MembershipRole::Owner => true,
            MembershipRole::Admin => $target === MembershipRole::Operator || $target === MembershipRole::Viewer,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    public function roles(MembershipRole $actor): array
    {
        return match ($actor) {
            MembershipRole::Owner => [MembershipRole::Admin->value, MembershipRole::Operator->value, MembershipRole::Viewer->value],
            MembershipRole::Admin => [MembershipRole::Operator->value, MembershipRole::Viewer->value],
            default => [],
        };
    }
}
