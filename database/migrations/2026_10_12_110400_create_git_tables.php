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
        Schema::create('git_connections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->string('provider', 16);
            $table->string('auth_method', 32);
            $table->string('account_label', 120);
            $table->string('external_account_id', 64);
            $table->string('external_account_type', 32);
            $table->unsignedBigInteger('credential_id');
            $table->string('base_url', 300);
            $table->jsonb('scopes')->default(new Expression("'[]'::jsonb"));
            $table->string('state', 32)->default('active');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->unique(['workspace_id', 'provider', 'external_account_id', 'base_url'], 'git_connections_account_uidx');
            $table->unique(['id', 'workspace_id'], 'git_connections_id_ws_uniq');
            $table->index(['workspace_id', 'state'], 'git_connections_state_idx');
            $table->foreign(['credential_id', 'workspace_id'], 'git_connections_cred_ws_fk')
                ->references(['id', 'workspace_id'])
                ->on('credentials')
                ->restrictOnDelete();
        });

        Schema::create('remote_repositories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('git_connection_id');
            $table->string('provider_repository_id', 64);
            $table->string('full_name', 200);
            $table->string('namespace', 160);
            $table->string('clone_url', 400);
            $table->string('default_branch', 255);
            $table->string('visibility', 16);
            $table->boolean('archived')->default(false);
            $table->boolean('disabled')->default(false);
            $table->timestampTz('last_seen_at')->nullable();
            $table->jsonb('provider_metadata')->default(new Expression("'{}'::jsonb"));
            $table->timestampsTz();

            $table->unique(['git_connection_id', 'provider_repository_id'], 'remote_repos_provider_uidx');
            $table->unique(['id', 'workspace_id'], 'remote_repos_id_ws_uniq');
            $table->index(['workspace_id', 'full_name'], 'remote_repos_name_idx');
            $table->foreign(['git_connection_id', 'workspace_id'], 'remote_repos_conn_ws_fk')
                ->references(['id', 'workspace_id'])
                ->on('git_connections')
                ->restrictOnDelete();
        });

        Schema::create('remote_branches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('remote_repository_id')->constrained('remote_repositories')->restrictOnDelete();
            $table->string('name', 255);
            $table->string('head_sha', 64);
            $table->boolean('protected')->default(false);
            $table->timestampTz('observed_at');
            $table->timestampsTz();

            $table->unique(['remote_repository_id', 'name'], 'remote_branches_name_uidx');
        });

        Schema::create('git_webhooks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('git_connection_id');
            $table->foreignId('remote_repository_id')->nullable()->constrained('remote_repositories')->restrictOnDelete();
            $table->string('provider', 16);
            $table->string('external_id', 64);
            $table->unsignedBigInteger('credential_id')->nullable();
            $table->boolean('enabled')->default(false);
            $table->jsonb('subscribed_events')->default(new Expression("'[]'::jsonb"));
            $table->timestampsTz();

            $table->unique(['git_connection_id', 'external_id'], 'git_webhooks_external_uidx');
            $table->unique(['id', 'workspace_id'], 'git_webhooks_id_ws_uniq');
            $table->foreign(['git_connection_id', 'workspace_id'], 'git_webhooks_conn_ws_fk')
                ->references(['id', 'workspace_id'])
                ->on('git_connections')
                ->restrictOnDelete();
            $table->foreign(['credential_id', 'workspace_id'], 'git_webhooks_cred_ws_fk')
                ->references(['id', 'workspace_id'])
                ->on('credentials')
                ->restrictOnDelete();
            $table->foreign(['remote_repository_id', 'workspace_id'], 'git_webhooks_repo_ws_fk')
                ->references(['id', 'workspace_id'])
                ->on('remote_repositories')
                ->restrictOnDelete();
        });

        Schema::create('git_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('git_webhook_id');
            $table->string('delivery_id', 128);
            $table->string('event_name', 64);
            $table->string('payload_digest', 64);
            $table->timestampTz('received_at');
            $table->timestampTz('verified_at')->nullable();
            $table->string('processing_state', 32);
            $table->boolean('signature_valid');
            $table->string('failure_code', 64)->nullable();
            $table->unsignedBigInteger('operation_job_id')->nullable();
            $table->timestampsTz();

            $table->unique(['git_webhook_id', 'delivery_id'], 'git_deliveries_uidx');
            $table->foreign(['git_webhook_id', 'workspace_id'], 'git_deliveries_hook_ws_fk')
                ->references(['id', 'workspace_id'])
                ->on('git_webhooks')
                ->restrictOnDelete();
            $table->foreign(['operation_job_id', 'workspace_id'], 'git_deliveries_job_ws_fk')
                ->references(['id', 'workspace_id'])
                ->on('operation_jobs')
                ->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
            alter table git_connections add constraint git_connections_provider_chk check (
                (provider = 'github' and base_url = 'https://github.com' and auth_method in ('oauth', 'personal_access_token', 'github_app'))
                or (provider = 'gitlab' and auth_method in ('oauth', 'personal_access_token', 'gitlab_token') and (base_url = 'https://gitlab.com' or rahandaz_public_https_url(base_url)))
            );
            alter table git_connections add constraint git_connections_account_chk check (
                external_account_id ~ '^[A-Za-z0-9_-]{1,64}$'
                and external_account_type in ('user', 'organization', 'group')
                and char_length(btrim(account_label)) between 1 and 120
            );
            alter table git_connections add constraint git_connections_scopes_chk check (jsonb_typeof(scopes) = 'array' and jsonb_array_length(scopes) <= 32);
            alter table git_connections add constraint git_connections_state_chk check (state in ('active', 'expired', 'revoked', 'error'));
            alter table git_connections add constraint git_connections_revoked_chk check (
                (state = 'revoked' and revoked_at is not null) or (state <> 'revoked')
            );

            alter table remote_repositories add constraint remote_repos_name_chk check (full_name ~ '^[A-Za-z0-9_.-]+(/[A-Za-z0-9_.-]+){1,5}$');
            alter table remote_repositories add constraint remote_repos_clone_chk check (rahandaz_repository_clone_url(clone_url));
            alter table remote_repositories add constraint remote_repos_visibility_chk check (visibility in ('public', 'private', 'internal'));
            alter table remote_repositories add constraint remote_repos_branch_chk check (default_branch ~ '^[A-Za-z0-9._/-]{1,255}$' and position('..' in default_branch) = 0);
            alter table remote_repositories add constraint remote_repos_meta_chk check (octet_length(provider_metadata::text) <= 8192);
            alter table remote_repositories add constraint remote_repos_provider_id_chk check (provider_repository_id ~ '^[A-Za-z0-9_-]{1,64}$');

            alter table remote_branches add constraint remote_branches_name_chk check (name ~ '^[A-Za-z0-9._/-]{1,255}$' and position('..' in name) = 0 and left(name, 1) <> '-');
            alter table remote_branches add constraint remote_branches_sha_chk check (rahandaz_commit_sha(head_sha));

            alter table git_webhooks add constraint git_webhooks_provider_chk check (provider in ('github', 'gitlab'));
            alter table git_webhooks add constraint git_webhooks_events_chk check (jsonb_typeof(subscribed_events) = 'array' and jsonb_array_length(subscribed_events) between 1 and 20);
            alter table git_webhooks add constraint git_webhooks_secret_chk check (enabled = false or credential_id is not null);
            alter table git_webhooks add constraint git_webhooks_external_chk check (external_id ~ '^[A-Za-z0-9_.:-]{1,64}$');

            alter table git_webhook_deliveries add constraint git_deliveries_digest_chk check (rahandaz_sha256(payload_digest));
            alter table git_webhook_deliveries add constraint git_deliveries_state_chk check (processing_state in ('received', 'rejected', 'queued', 'processed', 'ignored'));
            alter table git_webhook_deliveries add constraint git_deliveries_signature_chk check (
                (signature_valid = false and processing_state = 'rejected')
                or signature_valid = true
            );
            alter table git_webhook_deliveries add constraint git_deliveries_event_chk check (event_name ~ '^[a-z0-9_.:-]{1,64}$');

            create index git_deliveries_cursor_idx on git_webhook_deliveries (git_webhook_id, received_at desc, id desc);
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('git_webhook_deliveries');
        Schema::dropIfExists('git_webhooks');
        Schema::dropIfExists('remote_branches');
        Schema::dropIfExists('remote_repositories');
        Schema::dropIfExists('git_connections');
    }
};
