<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'local_host_id', 'upload_max_filesize', 'post_max_size', 'memory_limit', 'max_execution_time', 'max_input_time', 'max_input_vars', 'timezone'])]
class PhpServerProfile extends Model
{
    use BelongsToWorkspace, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'max_execution_time' => 'integer',
            'max_input_time' => 'integer',
            'max_input_vars' => 'integer',
        ];
    }

    public function localHost(): BelongsTo
    {
        return $this->belongsTo(LocalHost::class);
    }
}
