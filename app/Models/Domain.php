<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Projects\Enums\RoutingKind;
use App\Domain\Projects\Enums\TlsMode;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\DomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'environment_id', 'local_host_id', 'hostname', 'routing_kind', 'temporary_path', 'tls_mode', 'certificate_id', 'validation_status'])]
class Domain extends Model
{
    /** @use HasFactory<DomainFactory> */
    use BelongsToWorkspace, HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'routing_kind' => RoutingKind::class,
            'tls_mode' => TlsMode::class,
        ];
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function localHost(): BelongsTo
    {
        return $this->belongsTo(LocalHost::class);
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }
}
