<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Git\Enums\GitAuthMethod;
use App\Domain\Git\Enums\GitConnectionState;
use App\Domain\Git\Enums\GitProvider;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\GitConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'provider', 'auth_method', 'account_label', 'external_account_id', 'external_account_type', 'credential_id', 'base_url', 'scopes', 'state', 'expires_at', 'last_synced_at', 'revoked_at'])]
class GitConnection extends Model
{
    /** @use HasFactory<GitConnectionFactory> */
    use BelongsToWorkspace, HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => GitProvider::class,
            'auth_method' => GitAuthMethod::class,
            'state' => GitConnectionState::class,
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function repositories(): HasMany
    {
        return $this->hasMany(RemoteRepository::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(GitWebhook::class);
    }
}
