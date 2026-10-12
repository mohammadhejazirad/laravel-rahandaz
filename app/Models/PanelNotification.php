<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Notifications\Enums\NotificationLevel;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\PanelNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'recipient_id', 'type', 'level', 'title', 'body', 'read_at', 'action_path', 'operation_job_id', 'event_key'])]
class PanelNotification extends Model
{
    /** @use HasFactory<PanelNotificationFactory> */
    use BelongsToWorkspace, HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => NotificationLevel::class,
            'read_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function operationJob(): BelongsTo
    {
        return $this->belongsTo(OperationJob::class);
    }
}
