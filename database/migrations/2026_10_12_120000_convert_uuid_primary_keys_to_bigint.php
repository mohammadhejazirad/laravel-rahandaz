<?php

declare(strict_types=1);

use App\Infrastructure\Database\IdentityCutover;
use Illuminate\Database\Migrations\Migration;

/**
 * Databases that already applied the UUIDv7 primary-key migrations keep their
 * rows. Fresh installs create bigint id directly and this migration returns
 * without changing them. Down is refused: restoring UUID primary keys needs a
 * backup, not a reverse type change.
 */
return new class extends Migration
{
    public function up(): void
    {
        IdentityCutover::apply();
    }

    public function down(): void
    {
        throw new RuntimeException('The bigint primary key cutover is forward-only. Restore a backup to return to UUID primary keys.');
    }
};
