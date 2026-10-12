<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['resource_key', 'owner_job_id', 'attempt', 'fencing_token', 'expires_at', 'released_at'])]
class ExecutionLease extends Model
{
    use HasPublicUuid;

    protected $table = 'execution_leases';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'fencing_token' => 'integer',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function ownerJob(): BelongsTo
    {
        return $this->belongsTo(OperationJob::class, 'owner_job_id');
    }
}
