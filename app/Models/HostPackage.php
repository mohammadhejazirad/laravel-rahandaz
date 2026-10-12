<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidV7;
use Database\Factories\HostPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['host_fact_id', 'name', 'version', 'architecture'])]
class HostPackage extends Model
{
    /** @use HasFactory<HostPackageFactory> */
    use HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
        ];
    }

    public function fact(): BelongsTo
    {
        return $this->belongsTo(HostFact::class);
    }
}
