<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'channel',
    'installed_version',
    'latest_version',
    'update_available',
    'published_at',
    'notes_url',
    'release_url',
    'result',
    'etag',
    'checked_at',
    'error_code',
])]
class ReleaseCheck extends Model
{
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'update_available' => 'boolean',
            'published_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPresenterArray(): array
    {
        return [
            'result' => $this->result,
            'latest_version' => $this->latest_version,
            'update_available' => $this->update_available,
            'published_at' => $this->published_at?->toIso8601String(),
            'notes_url' => $this->notes_url,
            'release_url' => $this->release_url,
            'checked_at' => $this->checked_at?->toIso8601String(),
            'error_code' => $this->error_code,
        ];
    }
}
