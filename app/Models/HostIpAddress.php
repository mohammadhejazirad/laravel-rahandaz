<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\HostIpAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['host_network_interface_id', 'address', 'scope'])]
class HostIpAddress extends Model
{
    /** @use HasFactory<HostIpAddressFactory> */
    use HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
        ];
    }

    public function networkInterface(): BelongsTo
    {
        return $this->belongsTo(HostNetworkInterface::class, 'host_network_interface_id');
    }
}
