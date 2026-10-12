<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Setup\Enums\SetupComponentState;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\SetupComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['setup_session_id', 'catalog_key', 'version', 'required', 'selected', 'sort_order', 'operation_job_step_id', 'state', 'error_code'])]
class SetupComponent extends Model
{
    /** @use HasFactory<SetupComponentFactory> */
    use HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => SetupComponentState::class,
            'required' => 'boolean',
            'selected' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function setupSession(): BelongsTo
    {
        return $this->belongsTo(SetupSession::class);
    }

    public function operationJobStep(): BelongsTo
    {
        return $this->belongsTo(OperationJobStep::class);
    }
}
