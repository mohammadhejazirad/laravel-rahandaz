<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Databases\Enums\DatabaseEngine;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\DatabaseInstanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'local_host_id', 'managed_service_id', 'engine', 'version', 'port', 'connection_mode', 'endpoint_address', 'socket_path', 'bind_scope', 'ownership', 'observed_state', 'observed_at'])]
class DatabaseInstance extends Model
{
    /** @use HasFactory<DatabaseInstanceFactory> */
    use BelongsToWorkspace, HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'engine' => DatabaseEngine::class,
            'port' => 'integer',
            'protected' => 'boolean',
            'observed_at' => 'datetime',
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

    public function users(): HasMany
    {
        return $this->hasMany(DatabaseUser::class);
    }

    public function projectDatabases(): HasMany
    {
        return $this->hasMany(ProjectDatabase::class);
    }
}
