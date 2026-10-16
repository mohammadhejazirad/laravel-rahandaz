<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('git_provider_settings', fn (Blueprint $table) => $table->string('base_url', 300)->nullable());
        DB::statement("update git_provider_settings set base_url = case when provider = 'github' then 'https://github.com' else 'https://gitlab.com' end");
        DB::unprepared("alter table git_provider_settings drop constraint git_provider_settings_provider_check;
            alter table git_provider_settings add constraint git_provider_settings_provider_check check (provider in ('github', 'gitlab', 'gitlabSelf'));
            alter table git_provider_settings alter column base_url set not null;
            alter table git_provider_settings add constraint git_provider_settings_origin_check check (
                (provider = 'github' and base_url = 'https://github.com') or
                (provider = 'gitlab' and base_url = 'https://gitlab.com') or
                (provider = 'gitlabSelf' and rahandaz_public_https_url(base_url) and base_url <> 'https://gitlab.com')
            );");
    }

    public function down(): void
    {
        // Preserve configured accounts and encrypted credentials; do not silently delete them.
        if (DB::table('git_provider_settings')->where('provider', 'gitlabSelf')->exists()) {
            throw new RuntimeException('Remove self-managed settings explicitly before reverting this migration.');
        }
        DB::unprepared("alter table git_provider_settings drop constraint git_provider_settings_origin_check;
            alter table git_provider_settings drop constraint git_provider_settings_provider_check;
            alter table git_provider_settings add constraint git_provider_settings_provider_check check (provider in ('github', 'gitlab'));");
        Schema::table('git_provider_settings', fn (Blueprint $table) => $table->dropColumn('base_url'));
    }
};
