<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Git\Enums\WebhookProcessingState;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\GitWebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'git_webhook_id', 'delivery_id', 'event_name', 'payload_digest', 'received_at', 'verified_at', 'processing_state', 'signature_valid', 'failure_code', 'operation_job_id'])]
class GitWebhookDelivery extends Model
{
    /** @use HasFactory<GitWebhookDeliveryFactory> */
    use BelongsToWorkspace, HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processing_state' => WebhookProcessingState::class,
            'signature_valid' => 'boolean',
            'received_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(GitWebhook::class, 'git_webhook_id');
    }

    public function operationJob(): BelongsTo
    {
        return $this->belongsTo(OperationJob::class);
    }
}
