<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Accounts\Enums\UserStatus;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;

final class PanelListener
{
    public function state(): ?array
    {
        $path = (string) config('rahandaz.panel_listener_file');
        if (! is_file($path) || is_link($path)) {
            return null;
        }
        $state = json_decode((string) file_get_contents($path), true);
        if (! is_array($state) || ($state['schema_version'] ?? null) !== '1' || ($state['enabled'] ?? null) !== true
            || ($state['allow_public_http'] ?? null) !== true || ! filter_var($state['host'] ?? '', FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || ! is_int($state['port'] ?? null) || $state['port'] < 1 || $state['port'] > 65535) {
            return null;
        }

        return $state;
    }

    public function owner(User $user, Workspace $workspace): bool
    {
        return $user->status === UserStatus::Active && (string) $workspace->owner_id === (string) $user->id
            && Membership::query()->where('workspace_id', $workspace->id)->where('user_id', $user->id)->first()?->role === MembershipRole::Owner;
    }

    public function validPort(int $port): bool
    {
        return $port >= 1024 && $port <= 65535 && ! in_array($port, [2049, 3659, 4045, 5060, 5061, 6000, 6566, 6665, 6666, 6667, 6668, 6669, 6697, 10080], true);
    }

    public function origin(array $state, ?int $port = null): string
    {
        $number = $port ?? $state['port'];

        return 'http://'.$state['host'].($number === 80 ? '' : ':'.$number);
    }
}
