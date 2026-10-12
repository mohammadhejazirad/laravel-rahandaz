<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('username');
            $table->string('email')->nullable();
            $table->string('password');
            $table->string('status')->default('active');
            $table->string('locale', 16)->default('fa');
            $table->string('timezone', 64)->default('Asia/Tehran');
            $table->string('theme', 16)->default('system');
            $table->rememberToken();
            $table->timestampsTz();

            $table->unique('username');
            $table->unique('email');
        });

        DB::statement('alter table users add constraint users_username_lower check (username = lower(username))');
        DB::statement("alter table users add constraint users_username_posix check (username ~ '^[a-z0-9][a-z0-9_-]{2,31}$')");
        DB::statement('alter table users add constraint users_email_lower check (email is null or email = lower(email))');
        DB::statement("alter table users add constraint users_status check (status in ('active', 'suspended'))");
        DB::statement("alter table users add constraint users_theme check (theme in ('light', 'dark', 'system'))");

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
