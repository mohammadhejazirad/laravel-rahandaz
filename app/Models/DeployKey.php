<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Git\Enums\DeployKeyState;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\DeployKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'remote_repository_id', 'project_id', 'public_key', 'fingerprint', 'credential_id', 'read_only', 'provider_state', 'revoked_at'])]
class DeployKey extends Model
{
    /** @use HasFactory<DeployKeyFactory> */
    use BelongsToWorkspace, HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_only' => 'boolean',
            'provider_state' => DeployKeyState::class,
            'revoked_at' => 'datetime',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(RemoteRepository::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }
}
