<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Hosts\Enums\HostCompatibility;
use App\Domain\Hosts\Enums\HostState;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\LocalHostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'slug', 'display_name', 'hostname', 'state', 'compatibility', 'last_seen_at'])]
class LocalHost extends Model
{
    /** @use HasFactory<LocalHostFactory> */
    use BelongsToWorkspace, HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => HostState::class,
            'compatibility' => HostCompatibility::class,
            'protected' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function facts(): HasMany
    {
        return $this->hasMany(HostFact::class);
    }

    public function sshKeys(): HasMany
    {
        return $this->hasMany(HostSshKey::class);
    }

    public function firewallRules(): HasMany
    {
        return $this->hasMany(FirewallRule::class);
    }

    public function managedServices(): HasMany
    {
        return $this->hasMany(ManagedService::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function databaseInstances(): HasMany
    {
        return $this->hasMany(DatabaseInstance::class);
    }
}
