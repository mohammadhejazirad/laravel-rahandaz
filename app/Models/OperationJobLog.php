<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['operation_job_id', 'sequence', 'level', 'step_key', 'message', 'recorded_at'])]
class OperationJobLog extends Model
{
    use HasPublicUuid;

    protected $table = 'operation_job_logs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(OperationJob::class, 'operation_job_id');
    }
}
