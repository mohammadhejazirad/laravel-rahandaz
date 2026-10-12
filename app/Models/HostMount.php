<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\HostMountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['host_fact_id', 'device', 'mount_point', 'filesystem', 'capacity_bytes', 'used_bytes'])]
class HostMount extends Model
{
    /** @use HasFactory<HostMountFactory> */
    use HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity_bytes' => 'integer',
            'used_bytes' => 'integer',
        ];
    }

    public function fact(): BelongsTo
    {
        return $this->belongsTo(HostFact::class);
    }
}
