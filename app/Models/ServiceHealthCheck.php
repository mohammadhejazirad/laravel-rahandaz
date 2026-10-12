<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidV7;
use Database\Factories\ServiceHealthCheckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['managed_service_id', 'observed_at', 'status', 'error_code'])]
class ServiceHealthCheck extends Model
{
    /** @use HasFactory<ServiceHealthCheckFactory> */
    use HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
        ];
    }

    public function managedService(): BelongsTo
    {
        return $this->belongsTo(ManagedService::class);
    }
}
