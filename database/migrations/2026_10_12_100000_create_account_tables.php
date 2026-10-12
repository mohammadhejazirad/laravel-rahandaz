<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('display_name')->nullable();
            $table->jsonb('mobile_envelope')->nullable();
            $table->jsonb('national_id_envelope')->nullable();
            $table->boolean('force_password_change')->default(false);
            $table->timestampTz('policy_accepted_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('workspaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug');
            $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete();
            $table->jsonb('settings')->default(new Expression("'{}'::jsonb"));
            $table->timestampTz('setup_completed_at')->nullable();
            $table->timestampsTz();

            $table->unique('slug');
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role');
            $table->timestampsTz();

            $table->unique(['workspace_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        DB::statement("alter table memberships add constraint memberships_role check (role in ('owner', 'admin', 'operator', 'viewer'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('workspaces');
        Schema::dropIfExists('user_profiles');
    }
};
