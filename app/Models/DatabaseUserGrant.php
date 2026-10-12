<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Databases\Enums\DatabasePrivilege;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\DatabaseUserGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['database_instance_id', 'database_user_id', 'project_database_id', 'privilege'])]
class DatabaseUserGrant extends Model
{
    /** @use HasFactory<DatabaseUserGrantFactory> */
    use HasFactory, HasUuidV7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'privilege' => DatabasePrivilege::class,
        ];
    }

    public function databaseInstance(): BelongsTo
    {
        return $this->belongsTo(DatabaseInstance::class);
    }

    public function databaseUser(): BelongsTo
    {
        return $this->belongsTo(DatabaseUser::class);
    }

    public function projectDatabase(): BelongsTo
    {
        return $this->belongsTo(ProjectDatabase::class);
    }
}
