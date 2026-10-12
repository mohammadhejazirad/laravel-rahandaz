<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table managed_services add constraint managed_services_id_host_uniq unique (id, local_host_id)');

        Schema::create('database_instances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('local_host_id');
            $table->unsignedBigInteger('managed_service_id')->nullable();
            $table->string('engine', 32);
            $table->string('version', 32);
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('connection_mode', 16);
            $table->ipAddress('endpoint_address')->nullable();
            $table->string('socket_path', 180)->nullable();
            $table->string('bind_scope', 16)->default('loopback');
            $table->boolean('protected')->default(false);
            $table->string('ownership', 32);
            $table->string('observed_state', 32)->default('unknown');
            $table->timestampTz('observed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'workspace_id'], 'database_instances_id_ws_uniq');
            $table->index(['local_host_id', 'engine'], 'database_instances_host_idx');
            $table->foreign(['local_host_id', 'workspace_id'], 'db_instances_host_ws_fk')
                ->references(['id', 'workspace_id'])->on('local_hosts')->restrictOnDelete();
            $table->foreign(['managed_service_id', 'local_host_id'], 'db_instances_service_host_fk')
                ->references(['id', 'local_host_id'])->on('managed_services')->restrictOnDelete();
        });

        Schema::create('database_users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('database_instance_id');
            $table->unsignedBigInteger('workspace_id');
            $table->string('username', 63);
            $table->unsignedBigInteger('credential_id');
            $table->string('auth_method', 32);
            $table->string('status', 16)->default('active');
            $table->timestampsTz();

            $table->unique(['database_instance_id', 'username'], 'database_users_name_uidx');
            $table->unique(['id', 'database_instance_id'], 'database_users_id_inst_uniq');
            $table->foreign(['database_instance_id', 'workspace_id'], 'database_users_inst_ws_fk')
                ->references(['id', 'workspace_id'])->on('database_instances')->restrictOnDelete();
            $table->foreign(['credential_id', 'workspace_id'], 'database_users_cred_ws_fk')
                ->references(['id', 'workspace_id'])->on('credentials')->restrictOnDelete();
        });

        Schema::create('project_databases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('database_instance_id');
            $table->unsignedBigInteger('environment_id');
            $table->unsignedBigInteger('workspace_id');
            $table->string('name', 63);
            $table->unsignedBigInteger('owner_database_user_id');
            $table->string('status', 16)->default('requested');
            $table->boolean('protected')->default(false);
            $table->timestampsTz();

            $table->unique(['database_instance_id', 'name'], 'project_databases_name_uidx');
            $table->unique(['id', 'database_instance_id'], 'project_databases_id_inst_uniq');
            $table->unique(['id', 'workspace_id'], 'project_databases_id_ws_uniq');
            $table->index('environment_id');
            $table->foreign(['database_instance_id', 'workspace_id'], 'project_db_inst_ws_fk')
                ->references(['id', 'workspace_id'])->on('database_instances')->restrictOnDelete();
            $table->foreign(['environment_id', 'workspace_id'], 'project_db_env_ws_fk')
                ->references(['id', 'workspace_id'])->on('environments')->restrictOnDelete();
            $table->foreign(['owner_database_user_id', 'database_instance_id'], 'project_db_owner_fk')
                ->references(['id', 'database_instance_id'])->on('database_users')->restrictOnDelete();
        });

        Schema::create('database_user_grants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('database_instance_id');
            $table->unsignedBigInteger('database_user_id');
            $table->unsignedBigInteger('project_database_id');
            $table->string('privilege', 16);
            $table->timestampsTz();

            $table->unique(['database_user_id', 'project_database_id', 'privilege'], 'database_grants_uidx');
            $table->foreign(['database_user_id', 'database_instance_id'], 'database_grants_user_fk')
                ->references(['id', 'database_instance_id'])->on('database_users')->restrictOnDelete();
            $table->foreign(['project_database_id', 'database_instance_id'], 'database_grants_db_fk')
                ->references(['id', 'database_instance_id'])->on('project_databases')->restrictOnDelete();
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('local_host_id')->nullable();
            $table->unsignedBigInteger('project_database_id')->nullable();
            $table->string('backup_type', 32);
            $table->string('storage', 16);
            $table->string('artifact_path', 420);
            $table->string('checksum_sha256', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('encryption_status', 32);
            $table->unsignedBigInteger('credential_id')->nullable();
            $table->timestampTz('retention_until')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->string('state', 16);
            $table->unsignedBigInteger('operation_job_id')->nullable();
            $table->boolean('protected')->default(false);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'workspace_id'], 'backups_id_ws_uniq');
            $table->index(['workspace_id', 'state', 'created_at'], 'backups_state_idx');
            $table->foreign('workspace_id', 'backups_ws_fk')->references('id')->on('workspaces')->restrictOnDelete();
            $table->foreign('local_host_id', 'backups_host_fk')->references('id')->on('local_hosts')->restrictOnDelete();
            $table->foreign(['project_database_id', 'workspace_id'], 'backups_project_db_fk')
                ->references(['id', 'workspace_id'])->on('project_databases')->restrictOnDelete();
            $table->foreign(['credential_id', 'workspace_id'], 'backups_cred_ws_fk')
                ->references(['id', 'workspace_id'])->on('credentials')->restrictOnDelete();
            $table->foreign(['operation_job_id', 'workspace_id'], 'backups_job_ws_fk')
                ->references(['id', 'workspace_id'])->on('operation_jobs')->restrictOnDelete();
        });

        Schema::create('restore_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('backup_id');
            $table->unsignedBigInteger('workspace_id');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 16);
            $table->string('approval_state', 16)->default('pending');
            $table->unsignedBigInteger('operation_job_id')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->index(['workspace_id', 'created_at', 'id'], 'restore_ops_cursor_idx');
            $table->foreign(['backup_id', 'workspace_id'], 'restore_ops_backup_ws_fk')
                ->references(['id', 'workspace_id'])->on('backups')->restrictOnDelete();
            $table->foreign(['operation_job_id', 'workspace_id'], 'restore_ops_job_ws_fk')
                ->references(['id', 'workspace_id'])->on('operation_jobs')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
            alter table database_instances add constraint db_instances_engine_chk check (engine in ('postgresql', 'mysql', 'mariadb', 'mongodb', 'mssql', 'redis'));
            alter table database_instances add constraint db_instances_mode_chk check (
                (connection_mode = 'tcp' and endpoint_address is not null and port between 1 and 65535 and socket_path is null)
                or (connection_mode = 'socket' and socket_path is not null and endpoint_address is null and port is null)
            );
            alter table database_instances add constraint db_instances_bind_chk check (
                bind_scope in ('loopback', 'private', 'public')
                and (connection_mode <> 'tcp' or bind_scope <> 'loopback' or endpoint_address in ('127.0.0.1'::inet, '::1'::inet))
            );
            alter table database_instances add constraint db_instances_socket_chk check (
                socket_path is null or (socket_path ~ '^/(run|var/run|var/lib)/[A-Za-z0-9_./-]{1,160}$' and position('..' in socket_path) = 0)
            );
            alter table database_instances add constraint db_instances_owner_chk check (
                (ownership = 'control_plane' and protected = true) or ownership = 'project_host'
            );
            alter table database_instances add constraint db_instances_observed_chk check (observed_state in ('unknown', 'running', 'stopped', 'failed'));
            alter table database_instances add constraint db_instances_ownership_chk check (ownership in ('control_plane', 'project_host'));
            create unique index database_instances_port_uidx on database_instances (local_host_id, port) where connection_mode = 'tcp';
            create unique index database_instances_socket_uidx on database_instances (local_host_id, socket_path) where socket_path is not null;

            alter table database_users add constraint database_users_name_chk check (
                username ~ '^[a-z_][a-z0-9_]{0,62}$'
                and username !~ '^(postgres|root|rahandaz|mysql|admin|sa)$'
            );
            alter table database_users add constraint database_users_auth_chk check (auth_method in ('scram_sha256', 'mysql_native', 'mongodb_scram', 'sql_login', 'redis_acl'));
            alter table database_users add constraint database_users_status_chk check (status in ('active', 'disabled', 'revoked'));

            alter table project_databases add constraint project_databases_name_chk check (
                name ~ '^[a-z_][a-z0-9_]{0,62}$'
                and name !~ '^(postgres|template0|template1|rahandaz|mysql|information_schema|performance_schema|sys|admin|local)$'
            );
            alter table project_databases add constraint project_databases_status_chk check (status in ('requested', 'ready', 'disabled', 'dropped'));

            alter table database_user_grants add constraint database_grants_priv_chk check (privilege in ('connect', 'read', 'read_write', 'owner'));

            alter table backups add constraint backups_type_chk check (
                (backup_type = 'database' and project_database_id is not null)
                or (backup_type in ('control_plane', 'config', 'uploads') and project_database_id is null and local_host_id is not null)
            );
            alter table backups add constraint backups_storage_chk check (storage in ('local', 'offsite'));
            alter table backups add constraint backups_path_chk check (rahandaz_backup_artifact_path(artifact_path));
            alter table backups add constraint backups_state_chk check (state in ('running', 'succeeded', 'failed', 'expired', 'pruned'));
            alter table backups add constraint backups_encryption_chk check (encryption_status in ('envelope', 'unencrypted_local'));
            alter table backups add constraint backups_success_chk check (
                state <> 'succeeded' or (checksum_sha256 is not null and retention_until is not null and rahandaz_sha256(checksum_sha256))
            );
            alter table backups add constraint backups_checksum_chk check (checksum_sha256 is null or rahandaz_sha256(checksum_sha256));
            alter table backups add constraint backups_offsite_chk check (storage <> 'offsite' or credential_id is not null);
            alter table backups add constraint backups_time_chk check (finished_at is null or started_at is null or finished_at >= started_at);

            alter table restore_operations add constraint restore_ops_status_chk check (status in ('queued', 'running', 'succeeded', 'failed', 'cancelled'));
            alter table restore_operations add constraint restore_ops_approval_chk check (approval_state in ('pending', 'approved', 'rejected'));
            alter table restore_operations add constraint restore_ops_job_chk check (
                (operation_job_id is null or approval_state = 'approved')
                and (approval_state <> 'rejected' or operation_job_id is null)
            );
            alter table restore_operations add constraint restore_ops_failure_chk check (failure_code is null or failure_code ~ '^[A-Z0-9_]{1,64}$');

            create or replace function rahandaz_database_user_auth() returns trigger
            language plpgsql as $$
            declare
                engine text;
            begin
                select database_instances.engine into engine from database_instances where id = new.database_instance_id;
                if engine = 'postgresql' and new.auth_method is distinct from 'scram_sha256' then
                    raise exception 'postgresql users require scram_sha256' using errcode = '23514';
                elsif engine in ('mysql', 'mariadb') and new.auth_method is distinct from 'mysql_native' then
                    raise exception 'mysql users require mysql_native' using errcode = '23514';
                elsif engine = 'mongodb' and new.auth_method is distinct from 'mongodb_scram' then
                    raise exception 'mongodb users require mongodb_scram' using errcode = '23514';
                elsif engine = 'mssql' and new.auth_method is distinct from 'sql_login' then
                    raise exception 'mssql users require sql_login' using errcode = '23514';
                elsif engine = 'redis' and new.auth_method is distinct from 'redis_acl' then
                    raise exception 'redis users require redis_acl' using errcode = '23514';
                elsif engine is null then
                    raise exception 'database instance is missing' using errcode = '23503';
                end if;
                return new;
            end $$;

            create trigger database_users_auth before insert or update on database_users
                for each row execute function rahandaz_database_user_auth();
            create trigger database_instances_protect before delete on database_instances
                for each row execute function rahandaz_reject_protected_delete();
            create trigger project_databases_protect before delete on project_databases
                for each row execute function rahandaz_reject_protected_delete();
            create trigger backups_protect before delete on backups
                for each row execute function rahandaz_reject_protected_delete();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('restore_operations');
        Schema::dropIfExists('backups');
        Schema::dropIfExists('database_user_grants');
        Schema::dropIfExists('project_databases');
        Schema::dropIfExists('database_users');
        Schema::dropIfExists('database_instances');

        DB::unprepared(<<<'SQL'
            drop function if exists rahandaz_database_user_auth();
            alter table managed_services drop constraint if exists managed_services_id_host_uniq;
            SQL);
    }
};
