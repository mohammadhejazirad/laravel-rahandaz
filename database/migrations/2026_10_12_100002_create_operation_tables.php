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
        Schema::create('operation_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->string('type', 100);
            $table->string('resource_type', 100)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('idempotency_key');
            $table->string('request_hash', 64);
            $table->jsonb('plan')->default(new Expression("'{}'::jsonb"));
            $table->unsignedInteger('attempt')->default(0);
            $table->boolean('cancel_requested')->default(false);
            $table->timestampTz('heartbeat_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestampsTz();

            $table->unique(['workspace_id', 'actor_id', 'type', 'idempotency_key'], 'operation_jobs_idempotency_unique');
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('operation_job_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('operation_job_id')->constrained('operation_jobs')->restrictOnDelete();
            $table->string('key', 64);
            $table->string('status', 32);
            $table->unsignedInteger('timeout_seconds');
            $table->jsonb('checkpoint')->nullable();
            $table->unsignedInteger('attempt')->default(0);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->unique(['operation_job_id', 'key']);
        });

        Schema::create('operation_job_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('operation_job_id')->constrained('operation_jobs')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('level', 16);
            $table->string('step_key', 64)->nullable();
            $table->text('message');
            $table->timestampTz('recorded_at');
            $table->timestampsTz();

            $table->unique(['operation_job_id', 'sequence']);
        });

        Schema::create('operation_job_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('operation_job_id')->constrained('operation_jobs')->restrictOnDelete();
            $table->string('event_type', 64);
            $table->jsonb('payload')->default(new Expression("'{}'::jsonb"));
            $table->timestampTz('published_at')->nullable();
            $table->unsignedInteger('attempt')->default(0);
            $table->timestampTz('available_at');
            $table->timestampsTz();
        });

        Schema::create('execution_leases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('resource_key');
            $table->foreignId('owner_job_id')->constrained('operation_jobs')->restrictOnDelete();
            $table->unsignedInteger('attempt');
            $table->unsignedBigInteger('fencing_token');
            $table->timestampTz('expires_at');
            $table->timestampTz('released_at')->nullable();
            $table->timestampsTz();

            $table->index(['resource_key', 'fencing_token']);
        });

        DB::statement("alter table operation_jobs add constraint operation_jobs_status check (status in ('queued', 'running', 'waiting_retry', 'succeeded', 'failed', 'interrupted', 'cancelling', 'cancelled', 'reconciling'))");
        DB::statement("alter table operation_job_steps add constraint operation_job_steps_status check (status in ('queued', 'running', 'succeeded', 'failed', 'skipped'))");
        DB::statement('create index operation_jobs_active_idx on operation_jobs (status, created_at) where status not in (\'succeeded\', \'failed\', \'cancelled\')');
        DB::statement('create index operation_job_outbox_unpublished_idx on operation_job_outbox (available_at) where published_at is null');
        DB::statement('create unique index execution_leases_one_active_idx on execution_leases (resource_key) where released_at is null');
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_leases');
        Schema::dropIfExists('operation_job_outbox');
        Schema::dropIfExists('operation_job_logs');
        Schema::dropIfExists('operation_job_steps');
        Schema::dropIfExists('operation_jobs');
    }
};
