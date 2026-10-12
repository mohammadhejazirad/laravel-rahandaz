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
        Schema::create('resource_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('membership_id');
            $table->string('resource_type', 64);
            $table->uuid('resource_id');
            $table->string('permission', 32);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['membership_id', 'resource_type', 'resource_id', 'permission'], 'resource_grants_member_perm_uidx');
            $table->index(['workspace_id', 'resource_type', 'resource_id'], 'resource_grants_lookup_idx');
            $table->foreign(['membership_id', 'workspace_id'], 'resource_grants_member_ws_fk')
                ->references(['id', 'workspace_id'])
                ->on('memberships')
                ->cascadeOnDelete();
        });

        Schema::create('credential_access_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('credential_id');
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('operation_job_id')->nullable();
            $table->string('action', 32);
            $table->string('result', 16);
            $table->uuid('request_id')->nullable();
            $table->ipAddress('source_ip')->nullable();
            $table->timestampTz('recorded_at');
            $table->timestampsTz();

            $table->index(['credential_id', 'recorded_at', 'id'], 'credential_access_cursor_idx');
            $table->foreign(['credential_id', 'workspace_id'], 'cred_access_credential_ws_fk')
                ->references(['id', 'workspace_id'])
                ->on('credentials')
                ->restrictOnDelete();
            $table->foreign(['operation_job_id', 'workspace_id'], 'cred_access_job_ws_fk')
                ->references(['id', 'workspace_id'])
                ->on('operation_jobs')
                ->restrictOnDelete();
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->nullable()->constrained('workspaces')->restrictOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 100);
            $table->string('resource_type', 64);
            $table->uuid('resource_id')->nullable();
            $table->ipAddress('source_ip')->nullable();
            $table->uuid('request_id')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->foreignUuid('operation_job_id')->nullable()->constrained('operation_jobs')->restrictOnDelete();
            $table->string('result', 16);
            $table->jsonb('metadata')->default(new Expression("'{}'::jsonb"));
            $table->string('prev_hash', 64)->nullable();
            $table->string('event_hash', 64);
            $table->timestampTz('recorded_at');
            $table->timestampsTz();

            $table->unique('event_hash');
            $table->index(['workspace_id', 'recorded_at', 'id'], 'audit_events_cursor_idx');
            $table->foreign('prev_hash', 'audit_events_prev_hash_fk')
                ->references('event_hash')
                ->on('audit_events')
                ->restrictOnDelete();
        });

        Schema::create('panel_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->foreignUuid('recipient_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 64);
            $table->string('level', 16);
            $table->string('title', 160);
            $table->text('body');
            $table->timestampTz('read_at')->nullable();
            $table->string('action_path', 200)->nullable();
            $table->uuid('operation_job_id')->nullable();
            $table->string('event_key', 160);
            $table->timestampsTz();

            $table->unique('event_key');
            $table->index(['recipient_id', 'workspace_id', 'created_at', 'id'], 'panel_notifications_cursor_idx');
            $table->foreign(['operation_job_id', 'workspace_id'], 'panel_notif_job_ws_fk')
                ->references(['id', 'workspace_id'])
                ->on('operation_jobs')
                ->restrictOnDelete();
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('event_type', 64);
            $table->boolean('enabled')->default(true);
            $table->timestampsTz();

            $table->unique(['user_id', 'channel', 'event_type'], 'notification_pref_uidx');
        });

        Schema::create('application_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->nullable();
            $table->string('scope', 32);
            $table->string('key', 80);
            $table->string('value_type', 32);
            $table->text('value_text')->nullable();
            $table->bigInteger('value_integer')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->uuid('credential_id')->nullable();
            $table->timestampsTz();

            $table->foreign('workspace_id', 'application_settings_ws_fk')
                ->references('id')
                ->on('workspaces')
                ->restrictOnDelete();
            $table->foreign(['credential_id', 'workspace_id'], 'application_settings_cred_fk')
                ->references(['id', 'workspace_id'])
                ->on('credentials')
                ->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
            alter table resource_grants add constraint resource_grants_type_chk check (resource_type in ('project', 'environment', 'managed_service', 'database_instance', 'git_connection', 'local_host'));
            alter table resource_grants add constraint resource_grants_permission_chk check (permission in ('view', 'deploy', 'operate', 'manage_secrets', 'restore'));

            alter table credential_access_events add constraint credential_access_action_chk check (action in ('store', 'decrypt', 'rotate', 'revoke'));
            alter table credential_access_events add constraint credential_access_result_chk check (result in ('success', 'failure', 'denied'));

            alter table audit_events add constraint audit_events_result_chk check (result in ('success', 'failure', 'denied'));
            alter table audit_events add constraint audit_events_hash_chk check (rahandaz_sha256(event_hash) and (prev_hash is null or rahandaz_sha256(prev_hash)));
            alter table audit_events add constraint audit_events_metadata_size_chk check (octet_length(metadata::text) <= 16384);
            alter table audit_events add constraint audit_events_action_chk check (action ~ '^[a-z0-9]+(\.[a-z0-9_]+){0,4}$');

            create index panel_notifications_unread_idx on panel_notifications (recipient_id, workspace_id, created_at desc, id desc) where read_at is null;
            alter table panel_notifications add constraint panel_notifications_level_chk check (level in ('info', 'success', 'warning', 'danger'));
            alter table panel_notifications add constraint panel_notifications_body_chk check (char_length(body) between 1 and 2000);
            alter table panel_notifications add constraint panel_notifications_action_chk check (
                action_path is null
                or (action_path ~ '^/rahandaz/[A-Za-z0-9_./-]{0,180}$' and position('..' in action_path) = 0 and position('//' in action_path) = 0)
            );

            alter table notification_preferences add constraint notification_pref_channel_chk check (channel in ('panel', 'email'));

            alter table application_settings add constraint application_settings_scope_chk check (
                (scope = 'installation' and workspace_id is null)
                or (scope = 'workspace' and workspace_id is not null)
            );
            alter table application_settings add constraint application_settings_key_chk check (key = lower(key) and key ~ '^[a-z0-9]+(\.[a-z0-9_]+){0,6}$');
            alter table application_settings add constraint application_settings_value_chk check (
                (value_type = 'string' and value_text is not null and value_integer is null and value_boolean is null and credential_id is null)
                or (value_type = 'boolean' and value_boolean is not null and value_text is null and value_integer is null and credential_id is null)
                or (value_type = 'integer' and value_integer is not null and value_text is null and value_boolean is null and credential_id is null)
                or (value_type = 'credential_ref' and credential_id is not null and value_text is null and value_integer is null and value_boolean is null)
            );
            alter table application_settings add constraint application_settings_secret_key_chk check (
                key !~ '(secret|password|token|private_key|credential)' or value_type = 'credential_ref'
            );
            alter table application_settings add constraint application_settings_install_secret_chk check (
                scope <> 'installation' or value_type <> 'credential_ref'
            );
            create unique index application_settings_install_uidx on application_settings (key) where workspace_id is null;
            create unique index application_settings_ws_uidx on application_settings (workspace_id, key) where workspace_id is not null;

            create trigger credential_access_events_no_delete before delete on credential_access_events
                for each row execute function rahandaz_reject_audit_delete();
            create trigger audit_events_no_delete before delete on audit_events
                for each row execute function rahandaz_reject_audit_delete();
            SQL);

        DB::statement('create index audit_events_job_idx on audit_events (operation_job_id) where operation_job_id is not null');
    }

    public function down(): void
    {
        Schema::dropIfExists('application_settings');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('panel_notifications');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('credential_access_events');
        Schema::dropIfExists('resource_grants');
    }
};
