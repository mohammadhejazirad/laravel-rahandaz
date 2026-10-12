<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\DatabaseUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['database_instance_id', 'workspace_id', 'username', 'credential_id', 'auth_method', 'status'])]
class DatabaseUser extends Model
{
    /** @use HasFactory<DatabaseUserFactory> */
    use BelongsToWorkspace, HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
        ];
    }

    public function databaseInstance(): BelongsTo
    {
        return $this->belongsTo(DatabaseInstance::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function grants(): HasMany
    {
        return $this->hasMany(DatabaseUserGrant::class, 'database_user_id');
    }
}
