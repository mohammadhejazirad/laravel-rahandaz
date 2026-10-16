<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'provider', 'client_id', 'app_slug', 'credential_id', 'base_url'])]
class GitProviderSetting extends Model
{
    use BelongsToWorkspace, HasPublicUuid;

    public function authorizationVersion(): string
    {
        return hash('sha256', json_encode([$this->client_id, $this->app_slug, $this->base_url, $this->credential->fingerprint], JSON_THROW_ON_ERROR));
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }
}
