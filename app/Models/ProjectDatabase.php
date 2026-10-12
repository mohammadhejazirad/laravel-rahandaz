<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Databases\Enums\ProjectDatabaseStatus;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\ProjectDatabaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['database_instance_id', 'environment_id', 'workspace_id', 'name', 'owner_database_user_id', 'status'])]
class ProjectDatabase extends Model
{
    /** @use HasFactory<ProjectDatabaseFactory> */
    use BelongsToWorkspace, HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectDatabaseStatus::class,
            'protected' => 'boolean',
        ];
    }

    public function databaseInstance(): BelongsTo
    {
        return $this->belongsTo(DatabaseInstance::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(DatabaseUser::class, 'owner_database_user_id');
    }

    public function grants(): HasMany
    {
        return $this->hasMany(DatabaseUserGrant::class, 'project_database_id');
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }
}
