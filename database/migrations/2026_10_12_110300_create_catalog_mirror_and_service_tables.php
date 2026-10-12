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
        Schema::create('package_definitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('key', 64);
            $table->string('display_name', 120);
            $table->string('service_kind', 32);
            $table->jsonb('capability_flags')->default(new Expression("'{}'::jsonb"));
            $table->jsonb('conflicts')->default(new Expression("'[]'::jsonb"));
            $table->unsignedInteger('definition_version')->default(1);
            $table->boolean('control_plane_candidate')->default(false);
            $table->timestampsTz();

            $table->unique('key');
        });

        Schema::create('package_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('package_definition_id')->constrained('package_definitions')->restrictOnDelete();
            $table->string('version', 64);
            $table->string('os_codename', 64)->default('');
            $table->string('architecture', 32)->default('');
            $table->string('source_kind', 32);
            $table->string('checksum_sha256', 64)->nullable();
            $table->string('signing_fingerprint', 128)->nullable();
            $table->string('lifecycle', 32);
            $table->string('systemd_unit', 128)->nullable();
            $table->timestampsTz();

            $table->unique(
                ['package_definition_id', 'version', 'os_codename', 'architecture'],
                'package_versions_identity_uidx',
            );
        });

        Schema::create('managed_services', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('local_host_id');
            $table->foreignId('package_definition_id')->nullable()->constrained('package_definitions')->restrictOnDelete();
            $table->string('kind', 32);
            $table->string('instance_key', 64);
            $table->string('requested_version', 64)->nullable();
            $table->string('observed_version', 64)->nullable();
            $table->string('package_source', 64)->nullable();
            $table->string('systemd_unit', 128)->nullable();
            $table->string('runtime_state', 32)->default('unknown');
            $table->string('management', 32)->default('unmanaged');
            $table->boolean('protected')->default(false);
            $table->boolean('auto_start')->default(false);
            $table->string('health_status', 32)->default('unknown');
            $table->timestampTz('installed_at')->nullable();
            $table->timestampTz('observed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['local_host_id', 'instance_key'], 'managed_services_instance_uidx');
            $table->unique(['id', 'workspace_id'], 'managed_services_id_ws_uniq');
            $table->index(['local_host_id', 'runtime_state'], 'managed_services_runtime_idx');
            $table->foreign(['local_host_id', 'workspace_id'], 'managed_services_host_ws_fk')
                ->references(['id', 'workspace_id'])
                ->on('local_hosts')
                ->restrictOnDelete();
        });

        Schema::create('mirror_endpoints', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('provider', 80);
            $table->string('software_kind', 32);
            $table->string('country', 2)->nullable();
            $table->string('base_url', 300);
            $table->string('trust_status', 32);
            $table->jsonb('capabilities')->default(new Expression("'{}'::jsonb"));
            $table->jsonb('compatible_codenames')->default(new Expression("'[]'::jsonb"));
            $table->unsignedSmallInteger('priority_seed')->default(100);
            $table->string('disabled_reason', 160)->nullable();
            $table->timestampTz('last_checked_at')->nullable();
            $table->timestampsTz();

            $table->unique(['software_kind', 'base_url'], 'mirror_endpoints_kind_url_uidx');
        });

        Schema::create('mirror_health_checks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('mirror_endpoint_id')->constrained('mirror_endpoints')->restrictOnDelete();
            $table->timestampTz('observed_at');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->boolean('tls_ok');
            $table->boolean('signature_ok');
            $table->string('freshness', 32);
            $table->string('error_code', 64)->nullable();
            $table->timestampsTz();

            $table->index('mirror_endpoint_id');
        });

        Schema::create('mirror_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('local_host_id')->constrained('local_hosts')->restrictOnDelete();
            $table->string('software_kind', 32);
            $table->string('mode', 32);
            $table->foreignId('mirror_endpoint_id')->nullable()->constrained('mirror_endpoints')->restrictOnDelete();
            $table->string('state', 32);
            $table->timestampTz('applied_at')->nullable();
            $table->timestampTz('last_probe_at')->nullable();
            $table->unsignedBigInteger('rollback_revision_id')->nullable();
            $table->timestampsTz();

            $table->unique(['local_host_id', 'software_kind'], 'mirror_config_current_uidx');
        });

        Schema::create('mirror_configuration_revisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('mirror_configuration_id')->constrained('mirror_configurations')->restrictOnDelete();
            $table->foreignId('local_host_id')->constrained('local_hosts')->restrictOnDelete();
            $table->string('software_kind', 32);
            $table->string('mode', 32);
            $table->foreignId('mirror_endpoint_id')->nullable()->constrained('mirror_endpoints')->restrictOnDelete();
            $table->string('base_url_snapshot', 300)->nullable();
            $table->string('state', 32);
            $table->timestampTz('recorded_at');
            $table->timestampsTz();

            $table->index(['mirror_configuration_id', 'recorded_at', 'id'], 'mirror_revisions_cursor_idx');
        });

        Schema::table('mirror_configurations', function (Blueprint $table) {
            $table->foreign('rollback_revision_id', 'mirror_config_rollback_fk')
                ->references('id')
                ->on('mirror_configuration_revisions')
                ->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
            alter table package_definitions add constraint package_definitions_key_chk check (key ~ '^[a-z0-9][a-z0-9-]{1,62}$');
            alter table package_definitions add constraint package_definitions_kind_chk check (service_kind in ('web', 'runtime', 'database', 'cache', 'tool', 'security'));
            alter table package_definitions add constraint package_definitions_conflicts_chk check (jsonb_typeof(conflicts) = 'array' and octet_length(capability_flags::text) <= 4096);

            alter table package_versions add constraint package_versions_source_chk check (source_kind in ('ubuntu_apt', 'signed_repository', 'upstream_tarball'));
            alter table package_versions add constraint package_versions_lifecycle_chk check (lifecycle in ('active', 'untested', 'disabled', 'eol'));
            alter table package_versions add constraint package_versions_checksum_chk check (checksum_sha256 is null or rahandaz_sha256(checksum_sha256));

            alter table managed_services add constraint managed_services_kind_chk check (kind in ('web', 'runtime', 'database', 'cache', 'tool', 'security', 'other'));
            alter table managed_services add constraint managed_services_instance_chk check (instance_key ~ '^[a-z0-9][a-z0-9._-]{0,62}$');
            alter table managed_services add constraint managed_services_runtime_chk check (runtime_state in ('unknown', 'running', 'stopped', 'failed', 'inactive'));
            alter table managed_services add constraint managed_services_management_chk check (management in ('unmanaged', 'adopted', 'panel_installed'));
            alter table managed_services add constraint managed_services_health_chk check (health_status in ('unknown', 'healthy', 'degraded', 'unhealthy'));
            alter table managed_services add constraint managed_services_unit_chk check (systemd_unit is null or systemd_unit ~ '^[a-zA-Z0-9@:._-]{1,128}$');

            alter table mirror_endpoints add constraint mirror_endpoints_kind_chk check (software_kind in ('ubuntu_apt', 'composer', 'npm', 'pypi', 'docker_registry', 'php_runtime', 'node_runtime'));
            alter table mirror_endpoints add constraint mirror_endpoints_trust_chk check (trust_status in ('trusted', 'candidate', 'disabled'));
            alter table mirror_endpoints add constraint mirror_endpoints_url_chk check (rahandaz_public_https_url(base_url));
            alter table mirror_endpoints add constraint mirror_endpoints_country_chk check (country is null or country ~ '^[a-z]{2}$');
            alter table mirror_endpoints add constraint mirror_endpoints_lists_chk check (jsonb_typeof(capabilities) = 'object' and jsonb_typeof(compatible_codenames) = 'array');

            alter table mirror_health_checks add constraint mirror_health_freshness_chk check (freshness in ('unknown', 'fresh', 'stale', 'failed'));

            alter table mirror_configurations add constraint mirror_config_kind_chk check (software_kind in ('ubuntu_apt', 'composer', 'npm', 'pypi', 'docker_registry', 'php_runtime', 'node_runtime'));
            alter table mirror_configurations add constraint mirror_config_mode_chk check (mode in ('official', 'domestic_auto', 'domestic_manual', 'custom'));
            alter table mirror_configurations add constraint mirror_config_state_chk check (state in ('desired', 'applied', 'degraded', 'rolled_back'));
            alter table mirror_configurations add constraint mirror_config_endpoint_chk check (
                (mode = 'official' and mirror_endpoint_id is null)
                or (mode <> 'official' and mirror_endpoint_id is not null)
            );

            alter table mirror_configuration_revisions add constraint mirror_rev_kind_chk check (software_kind in ('ubuntu_apt', 'composer', 'npm', 'pypi', 'docker_registry', 'php_runtime', 'node_runtime'));
            alter table mirror_configuration_revisions add constraint mirror_rev_mode_chk check (mode in ('official', 'domestic_auto', 'domestic_manual', 'custom'));
            alter table mirror_configuration_revisions add constraint mirror_rev_state_chk check (state in ('desired', 'applied', 'degraded', 'rolled_back'));
            alter table mirror_configuration_revisions add constraint mirror_rev_url_chk check (base_url_snapshot is null or rahandaz_public_https_url(base_url_snapshot));

            create index mirror_health_cursor_idx on mirror_health_checks (mirror_endpoint_id, observed_at desc, id desc);

            create trigger managed_services_protect before delete on managed_services
                for each row execute function rahandaz_reject_protected_delete();
            SQL);
    }

    public function down(): void
    {
        Schema::table('mirror_configurations', function (Blueprint $table) {
            $table->dropForeign('mirror_config_rollback_fk');
        });

        Schema::dropIfExists('mirror_configuration_revisions');
        Schema::dropIfExists('mirror_configurations');
        Schema::dropIfExists('mirror_health_checks');
        Schema::dropIfExists('mirror_endpoints');
        Schema::dropIfExists('managed_services');
        Schema::dropIfExists('package_versions');
        Schema::dropIfExists('package_definitions');
    }
};
