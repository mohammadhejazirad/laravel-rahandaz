<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Jobs\PlanGuard;
use App\Domain\Setup\Enums\SetupState;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\SetupSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'owner_id', 'current_step', 'mode', 'answers', 'plan_hash', 'state', 'operation_job_id', 'error_code', 'started_at', 'finished_at'])]
class SetupSession extends Model
{
    /** @use HasFactory<SetupSessionFactory> */
    use BelongsToWorkspace, HasFactory, HasPublicUuid;

    protected static function booted(): void
    {
        static::saving(function (SetupSession $session): void {
            app(PlanGuard::class)->assertSafe($session->answers ?? []);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => SetupState::class,
            'answers' => 'array',
            'current_step' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function operationJob(): BelongsTo
    {
        return $this->belongsTo(OperationJob::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(SetupComponent::class);
    }
}
