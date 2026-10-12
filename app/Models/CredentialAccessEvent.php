<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Audit\Enums\CredentialAccessAction;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\CredentialAccessEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'credential_id', 'actor_id', 'operation_job_id', 'action', 'result', 'request_id', 'source_ip', 'recorded_at'])]
class CredentialAccessEvent extends Model
{
    /** @use HasFactory<CredentialAccessEventFactory> */
    use BelongsToWorkspace, HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => CredentialAccessAction::class,
            'result' => AuditResult::class,
            'recorded_at' => 'datetime',
        ];
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function operationJob(): BelongsTo
    {
        return $this->belongsTo(OperationJob::class);
    }
}
