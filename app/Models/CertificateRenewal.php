<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidV7;
use Database\Factories\CertificateRenewalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['certificate_id', 'operation_job_id', 'status', 'error_code', 'started_at', 'finished_at'])]
class CertificateRenewal extends Model
{
    /** @use HasFactory<CertificateRenewalFactory> */
    use HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    public function operationJob(): BelongsTo
    {
        return $this->belongsTo(OperationJob::class);
    }
}
