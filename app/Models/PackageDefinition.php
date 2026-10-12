<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Catalog\Enums\ServiceKind;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\PackageDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'display_name', 'service_kind', 'capability_flags', 'conflicts', 'definition_version', 'control_plane_candidate'])]
class PackageDefinition extends Model
{
    /** @use HasFactory<PackageDefinitionFactory> */
    use HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_kind' => ServiceKind::class,
            'capability_flags' => 'array',
            'conflicts' => 'array',
            'definition_version' => 'integer',
            'control_plane_candidate' => 'boolean',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PackageVersion::class);
    }
}
