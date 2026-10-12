<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidV7;
use Database\Factories\ScheduledOperationRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['schedule_id', 'operation_job_id', 'scheduled_for', 'state'])]
class ScheduledOperationRun extends Model
{
    /** @use HasFactory<ScheduledOperationRunFactory> */
    use HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function operationJob(): BelongsTo
    {
        return $this->belongsTo(OperationJob::class);
    }
}
