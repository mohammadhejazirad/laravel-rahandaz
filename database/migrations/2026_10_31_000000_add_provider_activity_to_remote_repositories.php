<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remote_repositories', function (Blueprint $table): void {
            $table->timestampTz('provider_activity_at')->nullable();
            $table->index(['git_connection_id', 'provider_activity_at', 'id'], 'remote_repos_activity_idx');
        });
    }

    public function down(): void
    {
        Schema::table('remote_repositories', function (Blueprint $table): void {
            $table->dropIndex('remote_repos_activity_idx');
            $table->dropColumn('provider_activity_at');
        });
    }
};
