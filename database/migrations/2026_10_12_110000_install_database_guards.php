<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            create or replace function rahandaz_sha256(value text) returns boolean
            language sql immutable as $$
                select value ~ '^[0-9a-f]{64}$'
            $$;

            create or replace function rahandaz_commit_sha(value text) returns boolean
            language sql immutable as $$
                select value ~ '^[0-9a-f]{40}$' or value ~ '^[0-9a-f]{64}$'
            $$;

            create or replace function rahandaz_openssh_public_key(value text) returns boolean
            language sql immutable as $$
                select value ~ '^(ssh-ed25519|ssh-rsa|ecdsa-sha2-nistp256|ecdsa-sha2-nistp384|sk-ssh-ed25519@openssh.com|sk-ecdsa-sha2-nistp256@openssh.com) [A-Za-z0-9+/]+={0,2}( [A-Za-z0-9_@.+-]{1,64}){0,1}$'
                    and value !~* 'private'
                    and position(E'\n' in value) = 0
            $$;

            create or replace function rahandaz_public_https_url(url text) returns boolean
            language plpgsql immutable as $$
            declare
                host text;
            begin
                if url is null or char_length(url) > 300 or position('@' in url) > 0 or position('?' in url) > 0 or position('#' in url) > 0 then
                    return false;
                end if;
                if url !~ '^https://[a-z0-9.-]+(:[0-9]{1,5}){0,1}(/[A-Za-z0-9._~/-]*){0,1}$' then
                    return false;
                end if;
                host := lower(substring(url from '^https://([^/:]+)'));
                if host is null or host = '' or host = 'localhost' or host like '%.localhost' or host like '%.internal' then
                    return false;
                end if;
                if host ~ '^[0-9.]+$' or host ~ ':' or host ~ '^(127\.|10\.|192\.168\.|169\.254\.|0\.|172\.(1[6-9]|2[0-9]|3[01])\.)' then
                    return false;
                end if;
                return true;
            end $$;

            create or replace function rahandaz_repository_clone_url(url text) returns boolean
            language plpgsql immutable as $$
            declare
                host text;
            begin
                if url is null or char_length(url) > 400 or url ~ '://[^/@[:space:]]+:[^/@[:space:]]+@' or url ~* '[?&](token|access_token|password|private_token)=' then
                    return false;
                end if;
                if url ~ '^https://' then
                    return rahandaz_public_https_url(url);
                end if;
                if url ~ '^git@[a-z0-9.-]+:[A-Za-z0-9_./-]+\.git$' then
                    host := substring(url from '^git@([a-z0-9.-]+):');
                    if host is null or host = 'localhost' or host like '%.localhost' or host like '%.internal' or host ~ '^[0-9.]+$' then
                        return false;
                    end if;
                    return true;
                end if;
                if url ~ '^ssh://git@[a-z0-9.-]+(:[0-9]{1,5}){0,1}/[A-Za-z0-9_./-]+\.git$' then
                    host := substring(url from '^ssh://git@([a-z0-9.-]+)');
                    if host is null or host = 'localhost' or host like '%.localhost' or host ~ '^[0-9.]+$' then
                        return false;
                    end if;
                    return true;
                end if;
                return false;
            end $$;

            create or replace function rahandaz_backup_artifact_path(path text) returns boolean
            language sql immutable as $$
                select path ~ '^/var/backups/rahandaz/[A-Za-z0-9_./-]+$'
                    and char_length(path) between 22 and 420
                    and position('..' in path) = 0
                    and position('//' in path) = 0
            $$;

            create or replace function rahandaz_project_path(path text) returns boolean
            language sql immutable as $$
                select path ~ '^/srv/rahandaz/projects/[a-z0-9][a-z0-9-]{0,48}/[a-z0-9][a-z0-9-]{0,32}(/[a-z0-9][a-z0-9_./-]{0,180}){0,1}$'
                    and position('..' in path) = 0
                    and position('//' in path) = 0
            $$;

            create or replace function rahandaz_reject_protected_delete() returns trigger
            language plpgsql as $$
            begin
                if old.protected then
                    raise exception 'protected resource cannot be deleted' using errcode = '42501';
                end if;
                return old;
            end $$;

            create or replace function rahandaz_reject_audit_delete() returns trigger
            language plpgsql as $$
            begin
                raise exception 'audit history cannot be deleted' using errcode = '42501';
            end $$;

            create or replace function rahandaz_freeze_commit_sha() returns trigger
            language plpgsql as $$
            begin
                if new.commit_sha is distinct from old.commit_sha then
                    raise exception 'commit_sha is immutable' using errcode = '23514';
                end if;
                return new;
            end $$;

            create or replace function rahandaz_grant_same_workspace() returns trigger
            language plpgsql as $$
            declare
                found bigint;
            begin
                if new.resource_type = 'project' then
                    select workspace_id into found from projects where id = new.resource_id;
                elsif new.resource_type = 'environment' then
                    select workspace_id into found from environments where id = new.resource_id;
                elsif new.resource_type = 'managed_service' then
                    select workspace_id into found from managed_services where id = new.resource_id;
                elsif new.resource_type = 'database_instance' then
                    select workspace_id into found from database_instances where id = new.resource_id;
                elsif new.resource_type = 'git_connection' then
                    select workspace_id into found from git_connections where id = new.resource_id;
                elsif new.resource_type = 'local_host' then
                    select workspace_id into found from local_hosts where id = new.resource_id;
                else
                    raise exception 'unsupported grant resource' using errcode = '23514';
                end if;

                if found is null or found is distinct from new.workspace_id then
                    raise exception 'grant resource is outside the workspace' using errcode = '23514';
                end if;

                return new;
            end $$;
            SQL);

        Schema::table('users', function (Blueprint $table) {
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampTz('last_activity_at')->nullable();
        });

        Schema::table('credentials', function (Blueprint $table) {
            $table->string('label', 120)->nullable();
            $table->string('algorithm', 64)->default('xchacha20poly1305-ietf');
            $table->timestampTz('rotated_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
        });

        Schema::table('operation_jobs', function (Blueprint $table) {
            $table->unsignedSmallInteger('priority')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(3);
        });

        DB::unprepared(<<<'SQL'
            alter table credentials add constraint credentials_algorithm_chk check (algorithm = 'xchacha20poly1305-ietf');
            alter table credentials add constraint credentials_label_chk check (label is null or char_length(btrim(label)) between 1 and 120);
            alter table credentials add constraint credentials_id_ws_uniq unique (id, workspace_id);
            create unique index credentials_active_blind_uidx on credentials (workspace_id, type, fingerprint) where revoked_at is null;

            alter table operation_jobs add constraint operation_jobs_priority_chk check (priority between 0 and 100);
            alter table operation_jobs add constraint operation_jobs_max_attempts_chk check (max_attempts between 1 and 20);
            alter table operation_jobs add constraint operation_jobs_id_ws_uniq unique (id, workspace_id);

            alter table memberships add constraint memberships_id_ws_uniq unique (id, workspace_id);
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            alter table memberships drop constraint if exists memberships_id_ws_uniq;
            drop index if exists credentials_active_blind_uidx;
            alter table credentials drop constraint if exists credentials_id_ws_uniq;
            alter table credentials drop constraint if exists credentials_label_chk;
            alter table credentials drop constraint if exists credentials_algorithm_chk;
            alter table operation_jobs drop constraint if exists operation_jobs_id_ws_uniq;
            alter table operation_jobs drop constraint if exists operation_jobs_max_attempts_chk;
            alter table operation_jobs drop constraint if exists operation_jobs_priority_chk;
            SQL);

        Schema::table('operation_jobs', function (Blueprint $table) {
            $table->dropColumn(['priority', 'max_attempts']);
        });

        Schema::table('credentials', function (Blueprint $table) {
            $table->dropColumn(['label', 'algorithm', 'rotated_at', 'expires_at', 'revoked_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_login_at', 'last_activity_at']);
        });

        DB::unprepared(<<<'SQL'
            drop function if exists rahandaz_grant_same_workspace();
            drop function if exists rahandaz_freeze_commit_sha();
            drop function if exists rahandaz_reject_audit_delete();
            drop function if exists rahandaz_reject_protected_delete();
            drop function if exists rahandaz_project_path(text);
            drop function if exists rahandaz_backup_artifact_path(text);
            drop function if exists rahandaz_repository_clone_url(text);
            drop function if exists rahandaz_public_https_url(text);
            drop function if exists rahandaz_openssh_public_key(text);
            drop function if exists rahandaz_commit_sha(text);
            drop function if exists rahandaz_sha256(text);
            SQL);
    }
};
