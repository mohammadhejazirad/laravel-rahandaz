<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'project_id', 'schedule_enabled', 'horizon_enabled', 'reverb_enabled', 'reverb_host', 'reverb_port', 'octane_enabled', 'octane_server', 'octane_host', 'octane_port', 'octane_workers', 'inertia_ssr_enabled', 'inertia_ssr_path', 'maintenance_enabled'])]
class ProjectLaravelSetting extends Model
{
    use BelongsToWorkspace, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'schedule_enabled' => 'boolean',
            'horizon_enabled' => 'boolean',
            'reverb_enabled' => 'boolean',
            'reverb_port' => 'integer',
            'octane_enabled' => 'boolean',
            'octane_port' => 'integer',
            'octane_workers' => 'integer',
            'inertia_ssr_enabled' => 'boolean',
            'maintenance_enabled' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
