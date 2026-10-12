<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidV7;
use Database\Factories\ServerPolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['local_host_id', 'worker_processes', 'worker_connections', 'keepalive_timeout', 'gzip', 'server_tokens', 'client_max_body_size_mb'])]
class ServerPolicy extends Model
{
    /** @use HasFactory<ServerPolicyFactory> */
    use HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'worker_processes' => 'integer',
            'worker_connections' => 'integer',
            'keepalive_timeout' => 'integer',
            'gzip' => 'boolean',
            'server_tokens' => 'boolean',
            'client_max_body_size_mb' => 'integer',
        ];
    }

    public function localHost(): BelongsTo
    {
        return $this->belongsTo(LocalHost::class);
    }
}
