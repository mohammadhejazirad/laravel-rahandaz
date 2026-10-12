<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Jobs\Enums\StepStatus;
use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operation_job_id',
    'key',
    'status',
    'timeout_seconds',
    'checkpoint',
    'attempt',
    'started_at',
    'finished_at',
])]
class OperationJobStep extends Model
{
    use HasUuidV7;

    protected $table = 'operation_job_steps';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StepStatus::class,
            'timeout_seconds' => 'integer',
            'checkpoint' => 'array',
            'attempt' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(OperationJob::class, 'operation_job_id');
    }
}
