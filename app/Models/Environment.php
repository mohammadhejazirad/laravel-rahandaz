<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Projects\Enums\DeploymentStrategy;
use App\Domain\Projects\Enums\EnvironmentStatus;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\EnvironmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'workspace_id', 'local_host_id', 'name', 'slug', 'branch_name', 'runtime_key', 'runtime_version', 'linux_user', 'release_path', 'shared_path', 'deployment_strategy', 'healthcheck_path', 'status', 'active_release_id'])]
class Environment extends Model
{
    /** @use HasFactory<EnvironmentFactory> */
    use BelongsToWorkspace, HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deployment_strategy' => DeploymentStrategy::class,
            'status' => EnvironmentStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function localHost(): BelongsTo
    {
        return $this->belongsTo(LocalHost::class);
    }

    public function activeRelease(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'active_release_id');
    }

    public function variables(): HasMany
    {
        return $this->hasMany(EnvironmentVariable::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    public function projectDatabases(): HasMany
    {
        return $this->hasMany(ProjectDatabase::class);
    }
}
