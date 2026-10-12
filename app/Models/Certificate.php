<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Projects\Enums\CertificateInstallState;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'local_host_id', 'mode', 'algorithm', 'install_state', 'issuer', 'not_before', 'not_after', 'fingerprint', 'credential_id', 'renewal_status'])]
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use BelongsToWorkspace, HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'install_state' => CertificateInstallState::class,
            'not_before' => 'datetime',
            'not_after' => 'datetime',
        ];
    }

    public function localHost(): BelongsTo
    {
        return $this->belongsTo(LocalHost::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(CertificateRenewal::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }
}
