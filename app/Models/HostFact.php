<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Hosts\Enums\HostCompatibility;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\HostFactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['local_host_id', 'observed_at', 'hostname', 'os_name', 'os_version', 'os_codename', 'kernel', 'architecture', 'virtualization', 'cpu_model', 'cpu_cores', 'ram_bytes', 'swap_bytes', 'uptime_seconds', 'load_1', 'systemd_health', 'compatibility', 'collector'])]
class HostFact extends Model
{
    /** @use HasFactory<HostFactFactory> */
    use HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'compatibility' => HostCompatibility::class,
            'cpu_cores' => 'integer',
            'ram_bytes' => 'integer',
            'swap_bytes' => 'integer',
        ];
    }

    public function localHost(): BelongsTo
    {
        return $this->belongsTo(LocalHost::class);
    }

    public function interfaces(): HasMany
    {
        return $this->hasMany(HostNetworkInterface::class);
    }

    public function disks(): HasMany
    {
        return $this->hasMany(HostDisk::class);
    }

    public function mounts(): HasMany
    {
        return $this->hasMany(HostMount::class);
    }

    public function ports(): HasMany
    {
        return $this->hasMany(HostPort::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(HostPackage::class);
    }
}
