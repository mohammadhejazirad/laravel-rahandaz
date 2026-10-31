<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Git\Enums\RepositoryVisibility;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\RemoteRepositoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'git_connection_id', 'provider_repository_id', 'full_name', 'namespace', 'clone_url', 'default_branch', 'visibility', 'archived', 'disabled', 'is_selected', 'last_seen_at', 'provider_metadata', 'provider_activity_at'])]
class RemoteRepository extends Model
{
    /** @use HasFactory<RemoteRepositoryFactory> */
    use BelongsToWorkspace, HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => RepositoryVisibility::class,
            'archived' => 'boolean',
            'disabled' => 'boolean',
            'is_selected' => 'boolean',
            'last_seen_at' => 'datetime',
            'provider_activity_at' => 'datetime',
            'provider_metadata' => 'array',
        ];
    }

    public function scopeRecentFirst(Builder $query): void
    {
        // Missing provider dates sort last; local sync/permission updates never affect activity.
        $query->orderByRaw('provider_activity_at desc nulls last')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function gitConnection(): BelongsTo
    {
        return $this->belongsTo(GitConnection::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(RemoteBranch::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
