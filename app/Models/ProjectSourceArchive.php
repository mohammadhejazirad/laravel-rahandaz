<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Projects\Enums\ProjectSourceArchiveStatus;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\ProjectSourceArchiveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['workspace_id', 'project_id', 'uploaded_by', 'disk', 'path', 'original_name', 'media_type', 'size_bytes', 'sha256', 'entry_count', 'uncompressed_bytes', 'status', 'expires_at'])]
class ProjectSourceArchive extends Model
{
    /** @use HasFactory<ProjectSourceArchiveFactory> */
    use BelongsToWorkspace, HasFactory, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'entry_count' => 'integer',
            'uncompressed_bytes' => 'integer',
            'status' => ProjectSourceArchiveStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class, 'source_archive_id');
    }

    public function latestDeployment(): HasOne
    {
        return $this->hasOne(Deployment::class, 'source_archive_id')->latestOfMany();
    }
}
