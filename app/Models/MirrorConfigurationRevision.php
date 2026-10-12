<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Mirrors\Enums\MirrorConfigState;
use App\Domain\Mirrors\Enums\MirrorMode;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\MirrorConfigurationRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['mirror_configuration_id', 'local_host_id', 'software_kind', 'mode', 'mirror_endpoint_id', 'base_url_snapshot', 'state', 'recorded_at'])]
class MirrorConfigurationRevision extends Model
{
    /** @use HasFactory<MirrorConfigurationRevisionFactory> */
    use HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => MirrorMode::class,
            'state' => MirrorConfigState::class,
            'recorded_at' => 'datetime',
        ];
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(MirrorConfiguration::class, 'mirror_configuration_id');
    }

    public function localHost(): BelongsTo
    {
        return $this->belongsTo(LocalHost::class);
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(MirrorEndpoint::class, 'mirror_endpoint_id');
    }
}
