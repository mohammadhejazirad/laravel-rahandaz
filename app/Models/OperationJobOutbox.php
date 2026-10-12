<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['operation_job_id', 'event_type', 'payload', 'published_at', 'attempt', 'available_at'])]
class OperationJobOutbox extends Model
{
    use HasUuidV7;

    protected $table = 'operation_job_outbox';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'published_at' => 'datetime',
            'attempt' => 'integer',
            'available_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(OperationJob::class, 'operation_job_id');
    }
}
