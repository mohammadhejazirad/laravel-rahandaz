<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Mirrors\Enums\TrustStatus;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\MirrorEndpointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['provider', 'software_kind', 'country', 'base_url', 'trust_status', 'capabilities', 'compatible_codenames', 'priority_seed', 'disabled_reason', 'last_checked_at'])]
class MirrorEndpoint extends Model
{
    /** @use HasFactory<MirrorEndpointFactory> */
    use HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trust_status' => TrustStatus::class,
            'capabilities' => 'array',
            'compatible_codenames' => 'array',
            'priority_seed' => 'integer',
            'last_checked_at' => 'datetime',
        ];
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(MirrorHealthCheck::class);
    }
}
