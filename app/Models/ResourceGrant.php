<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Access\Enums\GrantPermission;
use App\Domain\Access\Enums\GrantResourceType;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\ResourceGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'membership_id', 'resource_type', 'resource_id', 'permission', 'created_by'])]
class ResourceGrant extends Model
{
    /** @use HasFactory<ResourceGrantFactory> */
    use BelongsToWorkspace, HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resource_type' => GrantResourceType::class,
            'permission' => GrantPermission::class,
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Resolve the granted row from the fixed resource catalog.
     * A class name is never accepted from stored or request input.
     */
    public function resource(): ?Model
    {
        $type = $this->resource_type;

        if (! $type instanceof GrantResourceType) {
            return null;
        }

        $resource = match ($type) {
            GrantResourceType::Project => Project::query()->find($this->resource_id),
            GrantResourceType::Environment => Environment::query()->find($this->resource_id),
            GrantResourceType::ManagedService => ManagedService::query()->find($this->resource_id),
            GrantResourceType::DatabaseInstance => DatabaseInstance::query()->find($this->resource_id),
            GrantResourceType::GitConnection => GitConnection::query()->find($this->resource_id),
            GrantResourceType::LocalHost => LocalHost::query()->find($this->resource_id),
        };

        if ($resource === null) {
            return null;
        }

        if ((string) $resource->getAttribute('workspace_id') !== (string) $this->workspace_id) {
            return null;
        }

        return $resource;
    }
}
