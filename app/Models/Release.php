<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Projects\Enums\ReleaseState;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\ReleaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['environment_id', 'workspace_id', 'commit_sha', 'artifact_digest', 'directory_path', 'state', 'operation_job_id'])]
class Release extends Model
{
    /** @use HasFactory<ReleaseFactory> */
    use BelongsToWorkspace, HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => ReleaseState::class,
        ];
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function operationJob(): BelongsTo
    {
        return $this->belongsTo(OperationJob::class);
    }
}
