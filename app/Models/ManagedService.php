<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Catalog\Enums\ServiceHealthStatus;
use App\Domain\Catalog\Enums\ServiceKind;
use App\Domain\Catalog\Enums\ServiceManagement;
use App\Domain\Catalog\Enums\ServiceRuntimeState;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\ManagedServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'local_host_id', 'package_definition_id', 'kind', 'instance_key', 'requested_version', 'observed_version', 'package_source', 'systemd_unit', 'runtime_state', 'management', 'auto_start', 'health_status', 'installed_at', 'observed_at'])]
class ManagedService extends Model
{
    /** @use HasFactory<ManagedServiceFactory> */
    use BelongsToWorkspace, HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ServiceKind::class,
            'runtime_state' => ServiceRuntimeState::class,
            'management' => ServiceManagement::class,
            'health_status' => ServiceHealthStatus::class,
            'protected' => 'boolean',
            'auto_start' => 'boolean',
            'installed_at' => 'datetime',
            'observed_at' => 'datetime',
        ];
    }

    public function localHost(): BelongsTo
    {
        return $this->belongsTo(LocalHost::class);
    }

    public function packageDefinition(): BelongsTo
    {
        return $this->belongsTo(PackageDefinition::class);
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(ServiceHealthCheck::class);
    }
}
