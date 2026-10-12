<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Mirrors\Enums\MirrorConfigState;
use App\Domain\Mirrors\Enums\MirrorMode;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\MirrorConfigurationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['local_host_id', 'software_kind', 'mode', 'mirror_endpoint_id', 'state', 'applied_at', 'last_probe_at', 'rollback_revision_id'])]
class MirrorConfiguration extends Model
{
    /** @use HasFactory<MirrorConfigurationFactory> */
    use HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => MirrorMode::class,
            'state' => MirrorConfigState::class,
            'applied_at' => 'datetime',
            'last_probe_at' => 'datetime',
        ];
    }

    public function localHost(): BelongsTo
    {
        return $this->belongsTo(LocalHost::class);
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(MirrorEndpoint::class, 'mirror_endpoint_id');
    }

    public function rollbackRevision(): BelongsTo
    {
        return $this->belongsTo(MirrorConfigurationRevision::class, 'rollback_revision_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(MirrorConfigurationRevision::class);
    }
}
