<?php

declare(strict_types=1);

namespace App\Domain\Accounts;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Accounts\Enums\Theme;
use App\Domain\Accounts\Enums\UserStatus;
use App\Models\Membership;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AccountBootstrapper
{
    public function ownerExists(): bool
    {
        return Membership::query()->where('role', MembershipRole::Owner)->exists();
    }

    /**
     * @return array{username: string, password: string}|null
     */
    public function createInitialOwner(): ?array
    {
        return DB::transaction(function (): ?array {
            DB::select('select pg_advisory_xact_lock(?)', [814201]);

            if ($this->ownerExists()) {
                return null;
            }

            $username = 'rahandaz_'.Str::lower(Str::random(12));
            $password = Str::password(32);

            $user = User::query()->create([
                'username' => $username,
                'email' => null,
                'password' => $password,
                'status' => UserStatus::Active,
                'locale' => 'fa',
                'timezone' => (string) config('rahandaz.default_timezone'),
                'theme' => Theme::System,
            ]);

            UserProfile::query()->create([
                'user_id' => $user->id,
                'force_password_change' => true,
            ]);

            $workspace = Workspace::query()->create([
                'name' => 'راه‌انداز',
                'slug' => 'default',
                'owner_id' => $user->id,
                'settings' => [],
            ]);

            Membership::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => MembershipRole::Owner,
            ]);

            return [
                'username' => $username,
                'password' => $password,
            ];
        });
    }
}
