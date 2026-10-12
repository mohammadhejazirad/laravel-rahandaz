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
        Schema::create('schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->string('operation_key', 64);
            $table->string('timezone', 64);
            $table->string('cron_expression', 80);
            $table->timestampTz('next_run_at')->nullable();
            $table->timestampTz('last_run_at')->nullable();
            $table->boolean('enabled')->default(false);
            $table->string('overlap_policy', 16)->default('skip');
            $table->string('resource_type', 64)->nullable();
            $table->uuid('resource_id')->nullable();
            $table->timestampsTz();

            $table->index(['workspace_id', 'operation_key'], 'schedules_operation_idx');
        });

        Schema::create('scheduled_operation_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('schedule_id')->constrained('schedules')->restrictOnDelete();
            $table->foreignUuid('operation_job_id')->nullable()->constrained('operation_jobs')->restrictOnDelete();
            $table->timestampTz('scheduled_for');
            $table->string('state', 16);
            $table->timestampsTz();

            $table->unique(['schedule_id', 'scheduled_for'], 'scheduled_runs_slot_uidx');
        });

        Schema::create('metric_samples', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('local_host_id')->constrained('local_hosts')->restrictOnDelete();
            $table->foreignUuid('managed_service_id')->nullable()->constrained('managed_services')->restrictOnDelete();
            $table->string('source', 16);
            $table->timestampTz('collected_at');
            $table->timestampTz('observed_at');
            $table->decimal('cpu_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('ram_used_bytes')->nullable();
            $table->unsignedBigInteger('disk_used_bytes')->nullable();
            $table->decimal('load_1', 8, 2)->nullable();
            $table->boolean('healthy')->nullable();
            $table->timestampsTz();

            $table->index('local_host_id');
        });

        Schema::create('alert_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->foreignUuid('local_host_id')->constrained('local_hosts')->restrictOnDelete();
            $table->string('metric', 32);
            $table->decimal('threshold', 20, 4);
            $table->unsignedInteger('duration_seconds');
            $table->decimal('hysteresis', 20, 4)->default(0);
            $table->string('state', 16)->default('enabled');
            $table->string('dedup_key', 80);
            $table->timestampsTz();

            $table->unique(['workspace_id', 'local_host_id', 'metric', 'dedup_key'], 'alert_rules_identity_uidx');
        });

        Schema::create('alert_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('alert_rule_id')->constrained('alert_rules')->restrictOnDelete();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->string('state', 16);
            $table->timestampTz('started_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->string('dedup_key', 80);
            $table->string('summary', 500);
            $table->timestampsTz();

            $table->index(['workspace_id', 'started_at', 'id'], 'alert_events_cursor_idx');
        });

        Schema::create('service_health_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('managed_service_id')->constrained('managed_services')->restrictOnDelete();
            $table->timestampTz('observed_at');
            $table->string('status', 16);
            $table->string('error_code', 64)->nullable();
            $table->timestampsTz();

            $table->index('managed_service_id');
        });

        Schema::create('setup_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('current_step')->default(1);
            $table->string('mode', 16);
            $table->jsonb('answers')->default(new Expression("'{}'::jsonb"));
            $table->string('plan_hash', 64)->nullable();
            $table->string('state', 32)->default('in_progress');
            $table->foreignUuid('operation_job_id')->nullable()->constrained('operation_jobs')->restrictOnDelete();
            $table->string('error_code', 64)->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->index(['workspace_id', 'state'], 'setup_sessions_state_idx');
        });

        Schema::create('setup_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('setup_session_id')->constrained('setup_sessions')->restrictOnDelete();
            $table->string('catalog_key', 64);
            $table->string('version', 64)->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('selected')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignUuid('operation_job_step_id')->nullable()->constrained('operation_job_steps')->restrictOnDelete();
            $table->string('state', 16)->default('not_started');
            $table->string('error_code', 64)->nullable();
            $table->timestampsTz();

            $table->unique(['setup_session_id', 'catalog_key'], 'setup_components_key_uidx');
        });

        DB::unprepared(<<<'SQL'
            alter table schedules add constraint schedules_operation_chk check (operation_key in (
                'backup.database', 'backup.control_plane', 'metric.sample', 'certificate.renew', 'mirror.probe'
            ));
            alter table schedules add constraint schedules_cron_chk check (cron_expression ~ '^([0-9*,/-]{1,20} ){4}[0-9*,/-]{1,20}$');
            alter table schedules add constraint schedules_overlap_chk check (overlap_policy in ('skip', 'queue'));
            alter table schedules add constraint schedules_resource_chk check (
                resource_type is null or resource_type in ('project_database', 'local_host', 'certificate', 'mirror_endpoint')
            );
            create index schedules_due_idx on schedules (next_run_at) where enabled = true;

            alter table scheduled_operation_runs add constraint scheduled_runs_state_chk check (state in ('queued', 'skipped', 'started', 'finished'));

            alter table metric_samples add constraint metric_samples_source_chk check (source in ('host', 'service', 'queue', 'deployment'));
            alter table metric_samples add constraint metric_samples_cpu_chk check (cpu_percent is null or cpu_percent between 0 and 100);
            alter table metric_samples add constraint metric_samples_time_chk check (observed_at <= collected_at + interval '5 minutes');
            create index metric_samples_cursor_idx on metric_samples (local_host_id, collected_at desc, id desc);

            alter table alert_rules add constraint alert_rules_metric_chk check (metric in ('cpu', 'ram', 'disk', 'load', 'service_health', 'certificate_expiry', 'queue_depth'));
            alter table alert_rules add constraint alert_rules_duration_chk check (duration_seconds between 1 and 86400 and hysteresis >= 0);
            alter table alert_rules add constraint alert_rules_state_chk check (state in ('enabled', 'disabled'));

            alter table alert_events add constraint alert_events_state_chk check (state in ('firing', 'resolved'));
            alter table alert_events add constraint alert_events_resolve_chk check (
                (state = 'firing' and resolved_at is null) or (state = 'resolved' and resolved_at is not null and resolved_at >= started_at)
            );
            alter table alert_events add constraint alert_events_summary_chk check (char_length(summary) between 1 and 500);
            create unique index alert_events_one_firing_uidx on alert_events (alert_rule_id) where state = 'firing';

            alter table service_health_checks add constraint service_health_status_chk check (status in ('healthy', 'degraded', 'unhealthy', 'unknown'));
            create index service_health_cursor_idx on service_health_checks (managed_service_id, observed_at desc, id desc);

            alter table setup_sessions add constraint setup_sessions_step_chk check (current_step between 1 and 8);
            alter table setup_sessions add constraint setup_sessions_mode_chk check (mode in ('manual', 'automatic'));
            alter table setup_sessions add constraint setup_sessions_state_chk check (state in ('in_progress', 'completed', 'completed_with_errors', 'blocked', 'cancelled'));
            alter table setup_sessions add constraint setup_sessions_answers_chk check (jsonb_typeof(answers) = 'object' and octet_length(answers::text) <= 32768);
            alter table setup_sessions add constraint setup_sessions_plan_chk check (plan_hash is null or rahandaz_sha256(plan_hash));
            alter table setup_sessions add constraint setup_sessions_time_chk check (finished_at is null or finished_at >= started_at);
            create unique index setup_sessions_one_active_uidx on setup_sessions (workspace_id) where state = 'in_progress';

            alter table setup_components add constraint setup_components_key_chk check (catalog_key ~ '^[a-z0-9][a-z0-9.-]{0,62}$');
            alter table setup_components add constraint setup_components_state_chk check (state in ('not_started', 'ready', 'running', 'succeeded', 'failed', 'skipped', 'blocked'));

            create trigger resource_grants_same_workspace before insert or update on resource_grants
                for each row execute function rahandaz_grant_same_workspace();
            SQL);
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists resource_grants_same_workspace on resource_grants');

        Schema::dropIfExists('setup_components');
        Schema::dropIfExists('setup_sessions');
        Schema::dropIfExists('service_health_checks');
        Schema::dropIfExists('alert_events');
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('metric_samples');
        Schema::dropIfExists('scheduled_operation_runs');
        Schema::dropIfExists('schedules');
    }
};
