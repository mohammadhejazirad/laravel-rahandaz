<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Git\Enums\GitProvider;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\GitWebhookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'git_connection_id', 'remote_repository_id', 'provider', 'external_id', 'credential_id', 'enabled', 'subscribed_events'])]
class GitWebhook extends Model
{
    /** @use HasFactory<GitWebhookFactory> */
    use BelongsToWorkspace, HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => GitProvider::class,
            'enabled' => 'boolean',
            'subscribed_events' => 'array',
        ];
    }

    public function gitConnection(): BelongsTo
    {
        return $this->belongsTo(GitConnection::class);
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(RemoteRepository::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(GitWebhookDelivery::class);
    }
}
