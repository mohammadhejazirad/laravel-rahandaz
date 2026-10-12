<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Backups\Enums\BackupState;
use App\Domain\Backups\Enums\BackupType;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\BackupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'local_host_id', 'project_database_id', 'backup_type', 'storage', 'artifact_path', 'checksum_sha256', 'size_bytes', 'encryption_status', 'credential_id', 'retention_until', 'verified_at', 'state', 'operation_job_id', 'started_at', 'finished_at'])]
class Backup extends Model
{
    /** @use HasFactory<BackupFactory> */
    use BelongsToWorkspace, HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'backup_type' => BackupType::class,
            'state' => BackupState::class,
            'protected' => 'boolean',
            'size_bytes' => 'integer',
            'retention_until' => 'datetime',
            'verified_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function localHost(): BelongsTo
    {
        return $this->belongsTo(LocalHost::class);
    }

    public function projectDatabase(): BelongsTo
    {
        return $this->belongsTo(ProjectDatabase::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function operationJob(): BelongsTo
    {
        return $this->belongsTo(OperationJob::class);
    }

    public function restores(): HasMany
    {
        return $this->hasMany(RestoreOperation::class);
    }
}
