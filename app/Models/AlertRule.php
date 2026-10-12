<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\AlertRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'local_host_id', 'metric', 'threshold', 'duration_seconds', 'hysteresis', 'state', 'dedup_key'])]
class AlertRule extends Model
{
    /** @use HasFactory<AlertRuleFactory> */
    use BelongsToWorkspace, HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
        ];
    }

    public function localHost(): BelongsTo
    {
        return $this->belongsTo(LocalHost::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AlertEvent::class);
    }
}
