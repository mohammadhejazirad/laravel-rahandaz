<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Hosts\Enums\FirewallOrigin;
use App\Domain\Hosts\Enums\FirewallState;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\FirewallRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['local_host_id', 'direction', 'protocol', 'port_start', 'port_end', 'source_cidr', 'purpose', 'origin', 'state', 'observed_at', 'project_id'])]
class FirewallRule extends Model
{
    /** @use HasFactory<FirewallRuleFactory> */
    use HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => FirewallOrigin::class,
            'state' => FirewallState::class,
            'protected' => 'boolean',
            'port_start' => 'integer',
            'port_end' => 'integer',
            'observed_at' => 'datetime',
        ];
    }

    public function localHost(): BelongsTo
    {
        return $this->belongsTo(LocalHost::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
