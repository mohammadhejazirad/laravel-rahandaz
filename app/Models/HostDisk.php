<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidV7;
use Database\Factories\HostDiskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['host_fact_id', 'name', 'capacity_bytes', 'model', 'rotational'])]
class HostDisk extends Model
{
    /** @use HasFactory<HostDiskFactory> */
    use HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rotational' => 'boolean',
            'capacity_bytes' => 'integer',
        ];
    }

    public function fact(): BelongsTo
    {
        return $this->belongsTo(HostFact::class);
    }
}
