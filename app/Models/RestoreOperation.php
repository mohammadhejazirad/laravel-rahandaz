<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Backups\Enums\RestoreApproval;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\RestoreOperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['backup_id', 'workspace_id', 'actor_id', 'status', 'approval_state', 'operation_job_id', 'failure_code', 'started_at', 'finished_at'])]
class RestoreOperation extends Model
{
    /** @use HasFactory<RestoreOperationFactory> */
    use BelongsToWorkspace, HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approval_state' => RestoreApproval::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(Backup::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function operationJob(): BelongsTo
    {
        return $this->belongsTo(OperationJob::class);
    }
}
