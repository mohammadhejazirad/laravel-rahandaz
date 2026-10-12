<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Projects\Enums\DeploymentStatus;
use App\Domain\Projects\Enums\DeploymentTrigger;
use App\Domain\Projects\Enums\PhaseStatus;
use App\Domain\Projects\Enums\RollbackStatus;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\DeploymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['environment_id', 'workspace_id', 'remote_repository_id', 'commit_sha', 'trigger_type', 'actor_id', 'status', 'previous_release_id', 'target_release_id', 'artifact_digest', 'build_status', 'health_status', 'rollback_status', 'rollback_of_deployment_id', 'operation_job_id', 'failure_code', 'plan_hash', 'started_at', 'finished_at'])]
class Deployment extends Model
{
    /** @use HasFactory<DeploymentFactory> */
    use BelongsToWorkspace, HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger_type' => DeploymentTrigger::class,
            'status' => DeploymentStatus::class,
            'build_status' => PhaseStatus::class,
            'health_status' => PhaseStatus::class,
            'rollback_status' => RollbackStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(RemoteRepository::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function previousRelease(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'previous_release_id');
    }

    public function targetRelease(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'target_release_id');
    }

    public function rollbackOf(): BelongsTo
    {
        return $this->belongsTo(Deployment::class, 'rollback_of_deployment_id');
    }

    public function operationJob(): BelongsTo
    {
        return $this->belongsTo(OperationJob::class);
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(DeploymentHealthCheck::class);
    }

    public function scopeMutating(Builder $query): Builder
    {
        return $query->whereIn('status', [
            DeploymentStatus::Queued,
            DeploymentStatus::Running,
            DeploymentStatus::Verifying,
            DeploymentStatus::RollingBack,
        ]);
    }
}
