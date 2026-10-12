<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_source_archives', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('project_id');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('disk', 32);
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->string('media_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->unsignedInteger('entry_count');
            $table->unsignedBigInteger('uncompressed_bytes');
            $table->string('status', 16)->default('ready');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'workspace_id'], 'project_archives_id_ws_uniq');
            $table->unique(['project_id', 'sha256'], 'project_archives_project_sha_uidx');
            $table->unique(['disk', 'path'], 'project_archives_storage_uidx');
            $table->index(['workspace_id', 'created_at', 'id'], 'project_archives_cursor_idx');
            $table->foreign(['project_id', 'workspace_id'], 'project_archives_project_ws_fk')
                ->references(['id', 'workspace_id'])->on('projects')->restrictOnDelete();
        });

        Schema::table('deployments', function (Blueprint $table) {
            $table->string('source_type', 16)->default('git')->after('remote_repository_id');
            $table->unsignedBigInteger('source_archive_id')->nullable()->after('source_type');
            $table->string('commit_sha', 64)->nullable()->change();
            $table->foreign(['source_archive_id', 'workspace_id'], 'deployments_archive_ws_fk')
                ->references(['id', 'workspace_id'])->on('project_source_archives')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
            alter table project_source_archives add constraint project_archives_disk_chk check (disk = 'project_archives');
            alter table project_source_archives add constraint project_archives_path_chk check (
                path ~ '^workspaces/[0-9a-f-]{36}/projects/[0-9a-f-]{36}/[0-9a-f-]{36}\.zip$'
                and position('..' in path) = 0
            );
            alter table project_source_archives add constraint project_archives_name_chk check (
                char_length(original_name) between 1 and 255
                and original_name !~ '[\\/[:cntrl:]]'
            );
            alter table project_source_archives add constraint project_archives_media_chk check (char_length(media_type) between 1 and 100);
            alter table project_source_archives add constraint project_archives_size_chk check (
                size_bytes between 1 and 1073741824
                and entry_count between 1 and 100000
                and uncompressed_bytes >= 1
            );
            alter table project_source_archives add constraint project_archives_sha_chk check (rahandaz_sha256(sha256));
            alter table project_source_archives add constraint project_archives_status_chk check (status in ('ready', 'expired', 'deleted'));
            create trigger project_source_archives_public_uuid_guard before insert or update on project_source_archives
                for each row execute function rahandaz_guard_public_uuid();

            alter table deployments drop constraint deployments_sha_chk;
            alter table deployments add constraint deployments_source_chk check (
                (source_type = 'git' and source_archive_id is null and rahandaz_commit_sha(commit_sha))
                or
                (source_type = 'archive' and source_archive_id is not null and remote_repository_id is null and commit_sha is null and rahandaz_sha256(artifact_digest))
            );

            create or replace function rahandaz_deployment_source_scope() returns trigger
            language plpgsql as $$
            declare
                archive_project bigint;
                archive_workspace bigint;
                archive_digest text;
                environment_project bigint;
                environment_workspace bigint;
            begin
                if new.source_type <> 'archive' then
                    return new;
                end if;

                select project_id, workspace_id, sha256
                into archive_project, archive_workspace, archive_digest
                from project_source_archives
                where id = new.source_archive_id and status = 'ready';

                select project_id, workspace_id
                into environment_project, environment_workspace
                from environments
                where id = new.environment_id;

                if archive_project is null
                    or archive_project is distinct from environment_project
                    or archive_workspace is distinct from environment_workspace
                    or archive_workspace is distinct from new.workspace_id
                    or archive_digest is distinct from new.artifact_digest then
                    raise exception 'archive deployment source is outside the environment scope' using errcode = '23514';
                end if;

                return new;
            end $$;

            create trigger deployments_source_scope before insert or update on deployments
                for each row execute function rahandaz_deployment_source_scope();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            drop trigger if exists deployments_source_scope on deployments;
            drop function if exists rahandaz_deployment_source_scope();
            alter table deployments drop constraint if exists deployments_source_chk;
            do $$
            begin
                if exists (select 1 from deployments where source_type = 'archive') then
                    raise exception 'cannot roll back archive source schema while archive deployments exist';
                end if;
            end $$;
            alter table deployments alter column commit_sha set not null;
            alter table deployments add constraint deployments_sha_chk check (rahandaz_commit_sha(commit_sha));
            drop trigger if exists project_source_archives_public_uuid_guard on project_source_archives;
            SQL);

        Schema::table('deployments', function (Blueprint $table) {
            $table->dropForeign('deployments_archive_ws_fk');
            $table->dropColumn(['source_archive_id', 'source_type']);
        });

        Schema::dropIfExists('project_source_archives');
    }
};
