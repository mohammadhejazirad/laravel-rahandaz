<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Jobs\Enums\JobStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Domain job stored in PostgreSQL. The table is operation_jobs so it cannot
 * collide with Laravel's Redis-transport jobs table.
 */
#[Fillable([
    'workspace_id',
    'type',
    'resource_type',
    'resource_id',
    'actor_id',
    'status',
    'idempotency_key',
    'request_hash',
    'plan',
    'attempt',
    'cancel_requested',
    'heartbeat_at',
    'started_at',
    'finished_at',
    'error_code',
])]
class OperationJob extends Model
{
    use HasPublicUuid;

    protected $table = 'operation_jobs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
            'plan' => 'array',
            'attempt' => 'integer',
            'priority' => 'integer',
            'max_attempts' => 'integer',
            'cancel_requested' => 'boolean',
            'heartbeat_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(OperationJobStep::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OperationJobLog::class);
    }

    public function outboxMessages(): HasMany
    {
        return $this->hasMany(OperationJobOutbox::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }
}
