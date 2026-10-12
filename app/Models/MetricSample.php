<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Monitoring\Enums\MetricSource;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\MetricSampleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['local_host_id', 'managed_service_id', 'source', 'collected_at', 'observed_at', 'cpu_percent', 'ram_used_bytes', 'disk_used_bytes', 'load_1', 'healthy'])]
class MetricSample extends Model
{
    /** @use HasFactory<MetricSampleFactory> */
    use HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => MetricSource::class,
            'collected_at' => 'datetime',
            'observed_at' => 'datetime',
            'healthy' => 'boolean',
        ];
    }

    public function localHost(): BelongsTo
    {
        return $this->belongsTo(LocalHost::class);
    }

    public function managedService(): BelongsTo
    {
        return $this->belongsTo(ManagedService::class);
    }
}
