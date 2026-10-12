<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('local_host_id');
            $table->string('name', 120);
            $table->string('slug', 50);
            $table->string('description', 2000)->nullable();
            $table->string('declared_type', 32);
            $table->string('detected_type', 32)->nullable();
            $table->unsignedBigInteger('remote_repository_id')->nullable();
            $table->string('default_branch', 255);
            $table->unsignedBigInteger('credential_id')->nullable();
            $table->boolean('auto_deploy')->default(false);
            $table->boolean('zero_downtime_requested')->default(false);
            $table->string('migration_policy', 32)->default('expand_only');
            $table->unsignedSmallInteger('release_retention')->default(5);
            $table->string('healthcheck_path', 128)->default('/health');
            $table->string('status', 32)->default('draft');
            $table->boolean('protected')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['workspace_id', 'slug'], 'projects_ws_slug_uidx');
            $table->unique(['id', 'workspace_id'], 'projects_id_ws_uniq');
            $table->index(['workspace_id', 'status'], 'projects_status_idx');
            $table->foreign(['local_host_id', 'workspace_id'], 'projects_host_ws_fk')
                ->references(['id', 'workspace_id'])->on('local_hosts')->restrictOnDelete();
            $table->foreign(['remote_repository_id', 'workspace_id'], 'projects_repo_ws_fk')
                ->references(['id', 'workspace_id'])->on('remote_repositories')->restrictOnDelete();
            $table->foreign(['credential_id', 'workspace_id'], 'projects_cred_ws_fk')
                ->references(['id', 'workspace_id'])->on('credentials')->restrictOnDelete();
        });

        Schema::create('environments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('local_host_id');
            $table->string('name', 120);
            $table->string('slug', 32);
            $table->string('branch_name', 255);
            $table->string('runtime_key', 16)->nullable();
            $table->string('runtime_version', 32)->nullable();
            $table->string('linux_user', 32)->nullable();
            $table->string('release_path', 255);
            $table->string('shared_path', 255);
            $table->string('deployment_strategy', 32);
            $table->string('healthcheck_path', 128)->default('/health');
            $table->string('status', 32)->default('draft');
            $table->unsignedBigInteger('active_release_id')->nullable();
            $table->timestampsTz();

            $table->unique(['project_id', 'slug'], 'environments_project_slug_uidx');
            $table->unique(['id', 'workspace_id'], 'environments_id_ws_uniq');
            $table->unique(['id', 'local_host_id'], 'environments_id_host_uniq');
            $table->index(['workspace_id', 'status'], 'environments_status_idx');
            $table->foreign(['project_id', 'workspace_id'], 'environments_project_ws_fk')
                ->references(['id', 'workspace_id'])->on('projects')->restrictOnDelete();
            $table->foreign(['local_host_id', 'workspace_id'], 'environments_host_ws_fk')
                ->references(['id', 'workspace_id'])->on('local_hosts')->restrictOnDelete();
        });

        Schema::create('environment_variables', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('environment_id');
            $table->unsignedBigInteger('workspace_id');
            $table->string('key', 128);
            $table->boolean('is_secret');
            $table->text('value')->nullable();
            $table->unsignedBigInteger('credential_id')->nullable();
            $table->timestampsTz();

            $table->unique(['environment_id', 'key'], 'env_vars_key_uidx');
            $table->foreign(['environment_id', 'workspace_id'], 'env_vars_env_ws_fk')
                ->references(['id', 'workspace_id'])->on('environments')->restrictOnDelete();
            $table->foreign(['credential_id', 'workspace_id'], 'env_vars_cred_ws_fk')
                ->references(['id', 'workspace_id'])->on('credentials')->restrictOnDelete();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('local_host_id');
            $table->string('mode', 16);
            $table->string('algorithm', 32);
            $table->string('install_state', 32)->default('requested');
            $table->string('issuer', 160)->nullable();
            $table->timestampTz('not_before')->nullable();
            $table->timestampTz('not_after')->nullable();
            $table->string('fingerprint', 64)->nullable();
            $table->unsignedBigInteger('credential_id')->nullable();
            $table->string('renewal_status', 32)->default('none');
            $table->timestampsTz();

            $table->unique(['id', 'local_host_id'], 'certificates_id_host_uniq');
            $table->index(['local_host_id', 'not_after'], 'certificates_expiry_idx');
            $table->foreign(['local_host_id', 'workspace_id'], 'certificates_host_ws_fk')
                ->references(['id', 'workspace_id'])->on('local_hosts')->restrictOnDelete();
            $table->foreign(['credential_id', 'workspace_id'], 'certificates_cred_ws_fk')
                ->references(['id', 'workspace_id'])->on('credentials')->restrictOnDelete();
        });

        Schema::create('certificate_renewals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('certificate_id')->constrained('certificates')->restrictOnDelete();
            $table->foreignId('operation_job_id')->nullable()->constrained('operation_jobs')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('error_code', 64)->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->index(['certificate_id', 'created_at', 'id'], 'certificate_renewals_cursor_idx');
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('environment_id');
            $table->unsignedBigInteger('local_host_id');
            $table->string('hostname', 253);
            $table->string('routing_kind', 32);
            $table->string('temporary_path', 80)->nullable();
            $table->string('tls_mode', 16)->default('off');
            $table->unsignedBigInteger('certificate_id')->nullable();
            $table->string('validation_status', 16)->default('unset');
            $table->timestampsTz();

            $table->unique(['local_host_id', 'hostname'], 'domains_host_name_uidx');
            $table->foreign(['environment_id', 'workspace_id'], 'domains_env_ws_fk')
                ->references(['id', 'workspace_id'])->on('environments')->restrictOnDelete();
            $table->foreign(['environment_id', 'local_host_id'], 'domains_env_host_fk')
                ->references(['id', 'local_host_id'])->on('environments')->restrictOnDelete();
            $table->foreign(['certificate_id', 'local_host_id'], 'domains_cert_host_fk')
                ->references(['id', 'local_host_id'])->on('certificates')->restrictOnDelete();
        });

        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('environment_id');
            $table->unsignedBigInteger('workspace_id');
            $table->string('commit_sha', 64);
            $table->string('artifact_digest', 64)->nullable();
            $table->string('directory_path', 255)->nullable();
            $table->string('state', 32);
            $table->unsignedBigInteger('operation_job_id')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'environment_id'], 'releases_id_env_uniq');
            $table->foreign(['environment_id', 'workspace_id'], 'releases_env_ws_fk')
                ->references(['id', 'workspace_id'])->on('environments')->restrictOnDelete();
            $table->foreign(['operation_job_id', 'workspace_id'], 'releases_job_ws_fk')
                ->references(['id', 'workspace_id'])->on('operation_jobs')->restrictOnDelete();
        });

        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('environment_id');
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('remote_repository_id')->nullable();
            $table->string('commit_sha', 64);
            $table->string('trigger_type', 32);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 32);
            $table->unsignedBigInteger('previous_release_id')->nullable();
            $table->unsignedBigInteger('target_release_id')->nullable();
            $table->string('artifact_digest', 64)->nullable();
            $table->string('build_status', 16)->default('pending');
            $table->string('health_status', 16)->default('pending');
            $table->string('rollback_status', 16)->default('none');
            $table->unsignedBigInteger('rollback_of_deployment_id')->nullable();
            $table->unsignedBigInteger('operation_job_id')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->string('plan_hash', 64);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['environment_id', 'workspace_id'], 'deployments_env_ws_fk')
                ->references(['id', 'workspace_id'])->on('environments')->restrictOnDelete();
            $table->foreign(['remote_repository_id', 'workspace_id'], 'deployments_repo_ws_fk')
                ->references(['id', 'workspace_id'])->on('remote_repositories')->restrictOnDelete();
            $table->foreign(['previous_release_id', 'environment_id'], 'deployments_prev_release_fk')
                ->references(['id', 'environment_id'])->on('releases')->restrictOnDelete();
            $table->foreign(['target_release_id', 'environment_id'], 'deployments_target_release_fk')
                ->references(['id', 'environment_id'])->on('releases')->restrictOnDelete();
            $table->foreign(['operation_job_id', 'workspace_id'], 'deployments_job_ws_fk')
                ->references(['id', 'workspace_id'])->on('operation_jobs')->restrictOnDelete();
        });

        Schema::table('deployments', function (Blueprint $table) {
            $table->foreign('rollback_of_deployment_id', 'deployments_rollback_of_fk')
                ->references('id')->on('deployments')->restrictOnDelete();
        });

        Schema::create('deployment_health_checks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('deployment_id')->constrained('deployments')->restrictOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('healthy');
            $table->string('error_code', 64)->nullable();
            $table->timestampTz('observed_at');
            $table->timestampsTz();

            $table->unique(['deployment_id', 'sequence'], 'deployment_checks_seq_uidx');
        });

        Schema::table('environments', function (Blueprint $table) {
            $table->foreign(['active_release_id', 'id'], 'environments_active_release_fk')
                ->references(['id', 'environment_id'])->on('releases')->restrictOnDelete();
        });

        Schema::create('deploy_keys', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('remote_repository_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->text('public_key')->nullable();
            $table->string('fingerprint', 128);
            $table->unsignedBigInteger('credential_id')->nullable();
            $table->boolean('read_only')->default(true);
            $table->string('provider_state', 32);
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['remote_repository_id', 'workspace_id'], 'deploy_keys_repo_ws_fk')
                ->references(['id', 'workspace_id'])->on('remote_repositories')->restrictOnDelete();
            $table->foreign(['project_id', 'workspace_id'], 'deploy_keys_project_ws_fk')
                ->references(['id', 'workspace_id'])->on('projects')->restrictOnDelete();
            $table->foreign(['credential_id', 'workspace_id'], 'deploy_keys_cred_ws_fk')
                ->references(['id', 'workspace_id'])->on('credentials')->restrictOnDelete();
            $table->index(['workspace_id', 'provider_state'], 'deploy_keys_state_idx');
        });

        Schema::create('nginx_sites', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('local_host_id');
            $table->unsignedBigInteger('environment_id')->nullable();
            $table->string('site_role', 16);
            $table->string('server_name', 253);
            $table->unsignedBigInteger('certificate_id')->nullable();
            $table->unsignedSmallInteger('upload_limit_mb')->default(16);
            $table->boolean('gzip')->default(true);
            $table->boolean('websocket')->default(false);
            $table->boolean('access_log')->default(true);
            $table->boolean('force_https')->default(false);
            $table->unsignedInteger('config_revision')->default(1);
            $table->boolean('protected')->default(false);
            $table->timestampsTz();

            $table->foreign(['local_host_id', 'workspace_id'], 'nginx_sites_host_ws_fk')
                ->references(['id', 'workspace_id'])->on('local_hosts')->restrictOnDelete();
            $table->foreign(['environment_id', 'workspace_id'], 'nginx_sites_env_ws_fk')
                ->references(['id', 'workspace_id'])->on('environments')->restrictOnDelete();
            $table->foreign(['certificate_id', 'local_host_id'], 'nginx_sites_cert_host_fk')
                ->references(['id', 'local_host_id'])->on('certificates')->restrictOnDelete();
        });

        Schema::create('server_policies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('local_host_id')->unique()->constrained('local_hosts')->restrictOnDelete();
            $table->unsignedSmallInteger('worker_processes')->default(1);
            $table->unsignedInteger('worker_connections')->default(1024);
            $table->unsignedSmallInteger('keepalive_timeout')->default(65);
            $table->boolean('gzip')->default(true);
            $table->boolean('server_tokens')->default(false);
            $table->unsignedSmallInteger('client_max_body_size_mb')->default(16);
            $table->timestampsTz();
        });

        Schema::table('firewall_rules', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
            alter table projects add constraint projects_name_chk check (char_length(btrim(name)) between 1 and 120);
            alter table projects add constraint projects_slug_chk check (slug ~ '^[a-z0-9][a-z0-9-]{1,48}$');
            alter table projects add constraint projects_type_chk check (
                declared_type in ('laravel', 'php', 'static', 'node', 'nuxt', 'next', 'python', 'django', 'flask', 'fastapi', 'express', 'vue', 'react', 'nest', 'adonis')
                and (detected_type is null or detected_type in ('laravel', 'php', 'static', 'node', 'nuxt', 'next', 'python', 'django', 'flask', 'fastapi', 'express', 'vue', 'react', 'nest', 'adonis'))
            );
            alter table projects add constraint projects_branch_chk check (default_branch ~ '^[A-Za-z0-9._/-]{1,255}$' and position('..' in default_branch) = 0);
            alter table projects add constraint projects_policy_chk check (migration_policy in ('none', 'expand_only', 'maintenance') and release_retention between 1 and 20);
            alter table projects add constraint projects_health_chk check (healthcheck_path ~ '^/[A-Za-z0-9_./-]{0,120}$' and position('..' in healthcheck_path) = 0);
            alter table projects add constraint projects_status_chk check (status in ('draft', 'active', 'disabled', 'archived'));

            alter table environments add constraint environments_slug_chk check (slug ~ '^[a-z0-9][a-z0-9-]{1,31}$');
            alter table environments add constraint environments_branch_chk check (branch_name ~ '^[A-Za-z0-9._/-]{1,255}$' and position('..' in branch_name) = 0);
            alter table environments add constraint environments_runtime_chk check (runtime_key is null or runtime_key in ('php', 'node', 'python', 'static'));
            alter table environments add constraint environments_user_chk check (linux_user is null or linux_user ~ '^rhproj_[a-z0-9]{4,16}$');
            alter table environments add constraint environments_paths_chk check (rahandaz_project_path(release_path) and rahandaz_project_path(shared_path) and release_path <> shared_path);
            alter table environments add constraint environments_strategy_chk check (deployment_strategy in ('symlink_release', 'blue_green', 'maintenance'));
            alter table environments add constraint environments_health_chk check (healthcheck_path ~ '^/[A-Za-z0-9_./-]{0,120}$' and position('..' in healthcheck_path) = 0);
            alter table environments add constraint environments_status_chk check (status in ('draft', 'provisioning', 'active', 'degraded', 'disabled'));

            alter table environment_variables add constraint env_vars_key_chk check (key ~ '^[A-Z_][A-Z0-9_]{0,127}$');
            alter table environment_variables add constraint env_vars_secret_chk check (
                (is_secret = false and credential_id is null and value is not null and char_length(value) <= 4000)
                or (is_secret = true and value is null and credential_id is not null)
            );

            alter table certificates add constraint certificates_mode_chk check (mode in ('custom', 'acme', 'imported'));
            alter table certificates add constraint certificates_alg_chk check (algorithm in ('ecdsa_secp384r1', 'rsa_legacy'));
            alter table certificates add constraint certificates_install_chk check (install_state in ('requested', 'pending', 'installed', 'failed', 'expired'));
            alter table certificates add constraint certificates_renewal_chk check (renewal_status in ('none', 'scheduled', 'running', 'succeeded', 'failed'));
            alter table certificates add constraint certificates_window_chk check (not_before is null or not_after is null or not_after > not_before);
            alter table certificates add constraint certificates_installed_chk check (
                install_state <> 'installed' or (credential_id is not null and not_after is not null and rahandaz_sha256(fingerprint))
            );
            alter table certificates add constraint certificates_fp_chk check (fingerprint is null or rahandaz_sha256(fingerprint));
            create unique index certificates_fp_uidx on certificates (local_host_id, fingerprint) where fingerprint is not null;

            alter table certificate_renewals add constraint certificate_renewals_status_chk check (status in ('requested', 'running', 'succeeded', 'failed'));
            alter table certificate_renewals add constraint certificate_renewals_time_chk check (finished_at is null or started_at is null or finished_at >= started_at);

            alter table domains add constraint domains_host_chk check (hostname = lower(hostname) and hostname ~ '^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9]){0,1}(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9]){0,1})+$');
            alter table domains add constraint domains_routing_chk check (
                (routing_kind = 'temporary_invalid' and hostname ~ '\.local\.rahandaz\.invalid$')
                or (routing_kind = 'public_hostname' and hostname !~ '\.invalid$')
                or (routing_kind = 'temporary_path' and temporary_path is not null)
            );
            alter table domains add constraint domains_temp_path_chk check (temporary_path is null or temporary_path ~ '^/sites/[a-z0-9][a-z0-9-]{0,63}/$');
            alter table domains add constraint domains_tls_chk check (
                tls_mode in ('off', 'custom', 'acme', 'assigned')
                and (tls_mode <> 'off' or certificate_id is null)
            );
            alter table domains add constraint domains_validation_chk check (validation_status in ('unset', 'pending', 'valid', 'invalid'));
            create unique index domains_temp_path_uidx on domains (local_host_id, temporary_path) where temporary_path is not null;

            alter table releases add constraint releases_sha_chk check (rahandaz_commit_sha(commit_sha));
            alter table releases add constraint releases_state_chk check (state in ('building', 'ready', 'active', 'previous', 'failed', 'retained', 'pruned'));
            alter table releases add constraint releases_artifact_chk check (state = 'building' or rahandaz_sha256(artifact_digest));
            alter table releases add constraint releases_path_chk check (
                (state in ('building', 'failed', 'pruned') and directory_path is null)
                or (directory_path is not null and rahandaz_project_path(directory_path))
            );
            create unique index releases_one_active_uidx on releases (environment_id) where state = 'active';
            create index releases_cursor_idx on releases (environment_id, created_at desc, id desc);

            alter table deployments add constraint deployments_sha_chk check (rahandaz_commit_sha(commit_sha));
            alter table deployments add constraint deployments_trigger_chk check (trigger_type in ('manual', 'webhook_push', 'branch_update', 'scheduled', 'retry', 'rollback'));
            alter table deployments add constraint deployments_status_chk check (status in ('queued', 'running', 'verifying', 'succeeded', 'failed', 'cancelled', 'rolling_back', 'rolled_back'));
            alter table deployments add constraint deployments_phase_chk check (
                build_status in ('pending', 'running', 'succeeded', 'failed', 'skipped')
                and health_status in ('pending', 'running', 'succeeded', 'failed', 'skipped')
                and rollback_status in ('none', 'requested', 'running', 'succeeded', 'failed')
            );
            alter table deployments add constraint deployments_success_chk check (
                status <> 'succeeded' or (health_status = 'succeeded' and build_status in ('succeeded', 'skipped'))
            );
            alter table deployments add constraint deployments_rollback_link_chk check (
                (trigger_type = 'rollback' and rollback_of_deployment_id is not null)
                or (trigger_type <> 'rollback' and rollback_of_deployment_id is null)
            );
            alter table deployments add constraint deployments_release_distinct_chk check (
                previous_release_id is null or target_release_id is null or previous_release_id <> target_release_id
            );
            alter table deployments add constraint deployments_plan_chk check (rahandaz_sha256(plan_hash));
            alter table deployments add constraint deployments_digest_chk check (artifact_digest is null or rahandaz_sha256(artifact_digest));
            alter table deployments add constraint deployments_failure_chk check (failure_code is null or failure_code ~ '^[A-Z0-9_]{1,64}$');
            alter table deployments add constraint deployments_time_chk check (finished_at is null or started_at is null or finished_at >= started_at);
            create unique index deployments_one_mutation_uidx on deployments (environment_id) where status in ('queued', 'running', 'verifying', 'rolling_back');
            create index deployments_cursor_idx on deployments (environment_id, created_at desc, id desc);

            alter table deployment_health_checks add constraint deployment_checks_seq_chk check (sequence >= 1);
            alter table deployment_health_checks add constraint deployment_checks_http_chk check (status_code is null or status_code between 100 and 599);

            alter table deploy_keys add constraint deploy_keys_owner_chk check (project_id is not null or remote_repository_id is not null);
            alter table deploy_keys add constraint deploy_keys_readonly_chk check (read_only = true);
            alter table deploy_keys add constraint deploy_keys_state_chk check (provider_state in ('pending', 'registered', 'verified', 'rejected', 'revoked'));
            alter table deploy_keys add constraint deploy_keys_material_chk check (
                (provider_state = 'rejected' and credential_id is null)
                or (provider_state <> 'rejected' and credential_id is not null and public_key is not null and rahandaz_openssh_public_key(public_key))
            );
            alter table deploy_keys add constraint deploy_keys_fp_chk check (fingerprint ~ '^SHA256:[A-Za-z0-9+/]{43}$');
            create unique index deploy_keys_fp_uidx on deploy_keys (workspace_id, fingerprint) where revoked_at is null and provider_state <> 'rejected';

            alter table nginx_sites add constraint nginx_sites_role_chk check (
                (site_role = 'project' and environment_id is not null and protected = false)
                or (site_role = 'panel' and environment_id is null and protected = true)
            );
            alter table nginx_sites add constraint nginx_sites_https_chk check (force_https = false or certificate_id is not null);
            alter table nginx_sites add constraint nginx_sites_upload_chk check (upload_limit_mb between 1 and 256 and config_revision >= 1);
            alter table nginx_sites add constraint nginx_sites_name_chk check (server_name = lower(server_name));
            create unique index nginx_sites_env_uidx on nginx_sites (environment_id) where environment_id is not null;
            create unique index nginx_sites_panel_uidx on nginx_sites (local_host_id) where site_role = 'panel';

            alter table server_policies add constraint server_policies_workers_chk check (worker_processes between 1 and 64 and worker_connections between 64 and 65535);
            alter table server_policies add constraint server_policies_keepalive_chk check (keepalive_timeout between 1 and 300 and client_max_body_size_mb between 1 and 256);
            alter table server_policies add constraint server_policies_tokens_chk check (server_tokens = false);

            create trigger projects_protect before delete on projects
                for each row execute function rahandaz_reject_protected_delete();
            create trigger nginx_sites_protect before delete on nginx_sites
                for each row execute function rahandaz_reject_protected_delete();
            create trigger deployments_freeze_commit before update on deployments
                for each row execute function rahandaz_freeze_commit_sha();

            create or replace function rahandaz_firewall_project_same_host() returns trigger
            language plpgsql as $$
            declare
                project_host bigint;
            begin
                if new.project_id is null then
                    return new;
                end if;
                select local_host_id into project_host from projects where id = new.project_id;
                if project_host is null or project_host is distinct from new.local_host_id then
                    raise exception 'firewall rule project is on another host' using errcode = '23514';
                end if;
                return new;
            end $$;

            create trigger firewall_rules_project_host before insert or update on firewall_rules
                for each row execute function rahandaz_firewall_project_same_host();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            drop trigger if exists firewall_rules_project_host on firewall_rules;
            drop function if exists rahandaz_firewall_project_same_host();
            SQL);

        Schema::table('firewall_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('environments', function (Blueprint $table) {
            $table->dropForeign('environments_active_release_fk');
        });

        Schema::dropIfExists('server_policies');
        Schema::dropIfExists('nginx_sites');
        Schema::dropIfExists('deploy_keys');
        Schema::dropIfExists('deployment_health_checks');
        Schema::dropIfExists('deployments');
        Schema::dropIfExists('releases');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('certificate_renewals');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('environment_variables');
        Schema::dropIfExists('environments');
        Schema::dropIfExists('projects');
    }
};
