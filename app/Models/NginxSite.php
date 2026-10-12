<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\NginxSiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'local_host_id', 'environment_id', 'site_role', 'server_name', 'certificate_id', 'upload_limit_mb', 'gzip', 'websocket', 'access_log', 'force_https', 'config_revision'])]
class NginxSite extends Model
{
    /** @use HasFactory<NginxSiteFactory> */
    use BelongsToWorkspace, HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'upload_limit_mb' => 'integer',
            'gzip' => 'boolean',
            'websocket' => 'boolean',
            'access_log' => 'boolean',
            'force_https' => 'boolean',
            'protected' => 'boolean',
            'config_revision' => 'integer',
        ];
    }

    public function localHost(): BelongsTo
    {
        return $this->belongsTo(LocalHost::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }
}
