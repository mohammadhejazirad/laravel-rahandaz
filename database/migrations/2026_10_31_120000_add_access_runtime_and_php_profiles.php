<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->jsonb('sections')->nullable();
        });

        Schema::create('project_laravel_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->foreignId('project_id')->unique()->constrained('projects')->cascadeOnDelete();
            $table->boolean('schedule_enabled')->default(false);
            $table->boolean('horizon_enabled')->default(false);
            $table->boolean('reverb_enabled')->default(false);
            $table->string('reverb_host', 253)->default('127.0.0.1');
            $table->unsignedInteger('reverb_port')->default(8080);
            $table->boolean('octane_enabled')->default(false);
            $table->string('octane_server', 16)->default('swoole');
            $table->string('octane_host', 253)->default('127.0.0.1');
            $table->unsignedInteger('octane_port')->default(8000);
            $table->unsignedTinyInteger('octane_workers')->default(2);
            $table->boolean('inertia_ssr_enabled')->default(false);
            $table->string('inertia_ssr_path', 180)->default('bootstrap/ssr/ssr.js');
            $table->boolean('maintenance_enabled')->default(false);
            $table->timestampsTz();
            $table->unique(['id', 'workspace_id'], 'project_laravel_settings_id_ws_uniq');
            $table->foreign(['project_id', 'workspace_id'], 'project_laravel_settings_project_ws_fk')
                ->references(['id', 'workspace_id'])->on('projects')->cascadeOnDelete();
        });

        Schema::create('php_server_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->unique()->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('local_host_id')->constrained('local_hosts')->cascadeOnDelete();
            $table->string('upload_max_filesize', 8)->default('8M');
            $table->string('post_max_size', 8)->default('8M');
            $table->string('memory_limit', 8)->default('128M');
            $table->unsignedSmallInteger('max_execution_time')->default(30);
            $table->unsignedSmallInteger('max_input_time')->default(60);
            $table->unsignedSmallInteger('max_input_vars')->default(1000);
            $table->string('timezone', 64)->default('Asia/Tehran');
            $table->timestampsTz();
        });

        DB::statement("alter table project_laravel_settings add constraint project_laravel_octane_chk check (octane_server in ('swoole', 'roadrunner') and reverb_port between 1024 and 65535 and octane_port between 1024 and 65535 and octane_workers between 1 and 32)");
    }

    public function down(): void
    {
        Schema::dropIfExists('php_server_profiles');
        Schema::dropIfExists('project_laravel_settings');
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn('sections');
        });
    }
};
