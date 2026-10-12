<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Projects\Enums\MigrationPolicy;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Enums\ProjectType;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'local_host_id', 'name', 'slug', 'description', 'declared_type', 'detected_type', 'remote_repository_id', 'default_branch', 'credential_id', 'auto_deploy', 'zero_downtime_requested', 'migration_policy', 'release_retention', 'healthcheck_path', 'status', 'created_by'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use BelongsToWorkspace, HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'declared_type' => ProjectType::class,
            'detected_type' => ProjectType::class,
            'auto_deploy' => 'boolean',
            'zero_downtime_requested' => 'boolean',
            'migration_policy' => MigrationPolicy::class,
            'release_retention' => 'integer',
            'status' => ProjectStatus::class,
            'protected' => 'boolean',
        ];
    }

    public function localHost(): BelongsTo
    {
        return $this->belongsTo(LocalHost::class);
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(RemoteRepository::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function environments(): HasMany
    {
        return $this->hasMany(Environment::class);
    }

    public function deployKeys(): HasMany
    {
        return $this->hasMany(DeployKey::class);
    }
}
