<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_checks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('channel', 16);
            $table->string('installed_version', 64);
            $table->string('latest_version', 64)->nullable();
            $table->boolean('update_available')->default(false);
            $table->timestampTz('published_at')->nullable();
            $table->string('notes_url', 255)->nullable();
            $table->string('release_url', 255)->nullable();
            $table->string('result', 32);
            $table->string('etag', 255)->nullable();
            $table->timestampTz('checked_at');
            $table->string('error_code', 64)->nullable();
            $table->timestampsTz();

            $table->index('checked_at');
        });

        DB::unprepared(<<<'SQL'
            alter table release_checks add constraint release_checks_channel_chk check (channel in ('stable', 'beta', 'development'));
            alter table release_checks add constraint release_checks_version_chk check (
                installed_version ~ '^[0-9A-Za-z][0-9A-Za-z._-]{0,62}$'
                and (latest_version is null or latest_version ~ '^[0-9A-Za-z][0-9A-Za-z._-]{0,62}$')
            );
            alter table release_checks add constraint release_checks_result_chk check (
                result in ('ok', 'not_modified', 'rate_limited', 'failed', 'source_unconfigured', 'cached')
            );
            alter table release_checks add constraint release_checks_url_chk check (
                (notes_url is null or notes_url ~ '^https://[A-Za-z0-9._:/-]+$')
                and (release_url is null or release_url ~ '^https://[A-Za-z0-9._:/-]+$')
            );
            create trigger release_checks_public_uuid_guard before insert or update on release_checks
                for each row execute function rahandaz_guard_public_uuid();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('release_checks');
    }
};
