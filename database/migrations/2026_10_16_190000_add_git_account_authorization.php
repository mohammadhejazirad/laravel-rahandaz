<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('git_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->string('provider', 16);
            $table->string('client_id', 200);
            $table->string('app_slug', 100)->nullable();
            $table->unsignedBigInteger('credential_id');
            $table->timestampsTz();
            $table->unique(['workspace_id', 'provider']);
            $table->foreign(['credential_id', 'workspace_id'])->references(['id', 'workspace_id'])->on('credentials')->restrictOnDelete();
        });
        DB::unprepared("alter table git_provider_settings add constraint git_provider_settings_provider_check check (provider in ('github', 'gitlab')); create trigger git_provider_settings_public_uuid_guard before insert or update on git_provider_settings for each row execute function rahandaz_guard_public_uuid();");
        Schema::table('git_connections', function (Blueprint $table) {
            $table->string('installation_id', 32)->nullable();
        });
        Schema::table('remote_repositories', function (Blueprint $table) {
            $table->boolean('is_selected')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('remote_repositories', fn (Blueprint $table) => $table->dropColumn('is_selected'));
        Schema::table('git_connections', fn (Blueprint $table) => $table->dropColumn('installation_id'));
        Schema::dropIfExists('git_provider_settings');
    }
};
