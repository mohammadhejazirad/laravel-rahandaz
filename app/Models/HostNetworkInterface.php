<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidV7;
use Database\Factories\HostNetworkInterfaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['host_fact_id', 'name', 'mac_address', 'mtu', 'state'])]
class HostNetworkInterface extends Model
{
    /** @use HasFactory<HostNetworkInterfaceFactory> */
    use HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mtu' => 'integer',
        ];
    }

    public function fact(): BelongsTo
    {
        return $this->belongsTo(HostFact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(HostIpAddress::class);
    }
}
