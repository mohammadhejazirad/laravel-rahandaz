<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'operation_key', 'timezone', 'cron_expression', 'next_run_at', 'last_run_at', 'enabled', 'overlap_policy', 'resource_type', 'resource_id'])]
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use BelongsToWorkspace, HasFactory, HasUuidV7;

    protected $table = 'schedules';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ScheduledOperationRun::class);
    }
}
