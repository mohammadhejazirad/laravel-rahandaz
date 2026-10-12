<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidV7;
use Database\Factories\HostPortFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['host_fact_id', 'protocol', 'address', 'port', 'process_name'])]
class HostPort extends Model
{
    /** @use HasFactory<HostPortFactory> */
    use HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
        ];
    }

    public function fact(): BelongsTo
    {
        return $this->belongsTo(HostFact::class);
    }
}
