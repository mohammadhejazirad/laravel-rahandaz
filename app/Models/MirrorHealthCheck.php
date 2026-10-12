<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidV7;
use Database\Factories\MirrorHealthCheckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['mirror_endpoint_id', 'observed_at', 'latency_ms', 'tls_ok', 'signature_ok', 'freshness', 'error_code'])]
class MirrorHealthCheck extends Model
{
    /** @use HasFactory<MirrorHealthCheckFactory> */
    use HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'tls_ok' => 'boolean',
            'signature_ok' => 'boolean',
            'latency_ms' => 'integer',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(MirrorEndpoint::class, 'mirror_endpoint_id');
    }
}
