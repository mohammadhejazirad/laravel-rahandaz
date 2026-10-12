<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Catalog\Enums\PackageLifecycle;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\PackageVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['package_definition_id', 'version', 'os_codename', 'architecture', 'source_kind', 'checksum_sha256', 'signing_fingerprint', 'lifecycle', 'systemd_unit'])]
class PackageVersion extends Model
{
    /** @use HasFactory<PackageVersionFactory> */
    use HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lifecycle' => PackageLifecycle::class,
        ];
    }

    public function packageDefinition(): BelongsTo
    {
        return $this->belongsTo(PackageDefinition::class);
    }
}
