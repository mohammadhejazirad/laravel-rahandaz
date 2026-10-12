<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Accounts\Enums\Theme;
use App\Domain\Accounts\Enums\UserStatus;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['username', 'email', 'password', 'status', 'locale', 'timezone', 'theme'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuidV7, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            $user->username = Str::lower(trim($user->username));

            if (is_string($user->email)) {
                $normalized = Str::lower(trim($user->email));
                $user->email = $normalized === '' ? null : $normalized;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => UserStatus::class,
            'theme' => Theme::class,
            'last_login_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public static function findForLogin(string $identifier): ?self
    {
        $normalized = Str::lower(trim($identifier));

        if ($normalized === '') {
            return null;
        }

        return static::query()
            ->where('username', $normalized)
            ->orWhere('email', $normalized)
            ->first();
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class, 'actor_id');
    }

    public function currentMembership(): ?Membership
    {
        $this->loadMissing('memberships');

        return $this->memberships->first();
    }

    public function currentWorkspace(): ?Workspace
    {
        $membership = $this->currentMembership();

        if ($membership === null) {
            return null;
        }

        $membership->loadMissing('workspace');

        return $membership->workspace;
    }

    public function role(): ?MembershipRole
    {
        return $this->currentMembership()?->role;
    }
}
