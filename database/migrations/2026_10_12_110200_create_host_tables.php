<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_hosts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->string('slug', 64);
            $table->string('display_name', 120);
            $table->string('hostname', 253)->nullable();
            $table->string('state', 32)->default('pending_probe');
            $table->string('compatibility', 32)->default('untested');
            $table->boolean('protected')->default(true);
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();

            $table->unique(['workspace_id', 'slug'], 'local_hosts_ws_slug_uidx');
            $table->unique(['id', 'workspace_id'], 'local_hosts_id_ws_uniq');
            $table->index(['workspace_id', 'state'], 'local_hosts_state_idx');
        });

        Schema::create('host_facts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('local_host_id')->constrained('local_hosts')->restrictOnDelete();
            $table->timestampTz('observed_at');
            $table->string('hostname', 253)->nullable();
            $table->string('os_name', 64)->nullable();
            $table->string('os_version', 64)->nullable();
            $table->string('os_codename', 64)->nullable();
            $table->string('kernel', 128)->nullable();
            $table->string('architecture', 32)->nullable();
            $table->string('virtualization', 64)->nullable();
            $table->string('cpu_model', 160)->nullable();
            $table->unsignedSmallInteger('cpu_cores')->nullable();
            $table->unsignedBigInteger('ram_bytes')->nullable();
            $table->unsignedBigInteger('swap_bytes')->nullable();
            $table->unsignedBigInteger('uptime_seconds')->nullable();
            $table->decimal('load_1', 8, 2)->nullable();
            $table->string('systemd_health', 32)->nullable();
            $table->string('compatibility', 32);
            $table->string('collector', 32);
            $table->timestampsTz();

            $table->index('local_host_id');
        });

        Schema::create('host_network_interfaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('host_fact_id')->constrained('host_facts')->cascadeOnDelete();
            $table->string('name', 32);
            $table->macAddress('mac_address')->nullable();
            $table->unsignedInteger('mtu')->nullable();
            $table->string('state', 32);
            $table->timestampsTz();

            $table->unique(['host_fact_id', 'name'], 'host_ifaces_fact_name_uidx');
        });

        Schema::create('host_ip_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('host_network_interface_id')->constrained('host_network_interfaces')->cascadeOnDelete();
            $table->ipAddress('address');
            $table->string('scope', 32);
            $table->timestampsTz();

            $table->unique(['host_network_interface_id', 'address'], 'host_ips_iface_addr_uidx');
        });

        Schema::create('host_disks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('host_fact_id')->constrained('host_facts')->cascadeOnDelete();
            $table->string('name', 64);
            $table->unsignedBigInteger('capacity_bytes');
            $table->string('model', 160)->nullable();
            $table->boolean('rotational')->nullable();
            $table->timestampsTz();

            $table->unique(['host_fact_id', 'name'], 'host_disks_fact_name_uidx');
        });

        Schema::create('host_mounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('host_fact_id')->constrained('host_facts')->cascadeOnDelete();
            $table->string('device', 160);
            $table->string('mount_point', 255);
            $table->string('filesystem', 32);
            $table->unsignedBigInteger('capacity_bytes');
            $table->unsignedBigInteger('used_bytes');
            $table->timestampsTz();

            $table->unique(['host_fact_id', 'mount_point'], 'host_mounts_fact_path_uidx');
        });

        Schema::create('host_ports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('host_fact_id')->constrained('host_facts')->cascadeOnDelete();
            $table->string('protocol', 8);
            $table->ipAddress('address');
            $table->unsignedSmallInteger('port');
            $table->string('process_name', 64)->nullable();
            $table->timestampsTz();

            $table->unique(['host_fact_id', 'protocol', 'address', 'port'], 'host_ports_listen_uidx');
        });

        Schema::create('host_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('host_fact_id')->constrained('host_facts')->cascadeOnDelete();
            $table->string('name', 128);
            $table->string('version', 128);
            $table->string('architecture', 32)->default('unknown');
            $table->timestampsTz();

            $table->unique(['host_fact_id', 'name', 'version', 'architecture'], 'host_packages_fact_pkg_uidx');
        });

        Schema::create('host_ssh_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('local_host_id')->constrained('local_hosts')->restrictOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('linux_account', 32);
            $table->text('public_key');
            $table->string('fingerprint', 128);
            $table->string('label', 120);
            $table->boolean('enabled')->default(true);
            $table->foreignUuid('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('last_verified_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['local_host_id', 'enabled'], 'host_ssh_keys_host_idx');
        });

        Schema::create('firewall_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('local_host_id')->constrained('local_hosts')->restrictOnDelete();
            $table->string('direction', 8);
            $table->string('protocol', 8);
            $table->unsignedSmallInteger('port_start')->nullable();
            $table->unsignedSmallInteger('port_end')->nullable();
            $table->string('purpose', 160);
            $table->string('origin', 32);
            $table->string('state', 32);
            $table->boolean('protected')->default(false);
            $table->timestampTz('observed_at')->nullable();
            $table->timestampsTz();

            $table->index(['local_host_id', 'origin', 'state'], 'firewall_rules_host_idx');
        });

        DB::statement('alter table firewall_rules add column source_cidr cidr not null');

        DB::unprepared(<<<'SQL'
            alter table local_hosts add constraint local_hosts_slug_chk check (slug ~ '^[a-z0-9][a-z0-9-]{1,62}$');
            alter table local_hosts add constraint local_hosts_state_chk check (state in ('pending_probe', 'compatible', 'degraded', 'unsupported', 'disabled'));
            alter table local_hosts add constraint local_hosts_compat_chk check (compatibility in ('untested', 'supported', 'unsupported'));

            alter table host_facts add constraint host_facts_compat_chk check (compatibility in ('untested', 'supported', 'unsupported'));
            alter table host_facts add constraint host_facts_collector_chk check (collector in ('readonly_probe', 'scheduled_sample'));
            alter table host_facts add constraint host_facts_cores_chk check (cpu_cores is null or cpu_cores >= 1);
            alter table host_facts add constraint host_facts_health_chk check (systemd_health is null or systemd_health in ('unknown', 'running', 'degraded', 'failed'));

            alter table host_network_interfaces add constraint host_ifaces_name_chk check (name ~ '^[A-Za-z0-9_.:-]{1,32}$');
            alter table host_network_interfaces add constraint host_ifaces_mtu_chk check (mtu is null or mtu between 68 and 65535);
            alter table host_network_interfaces add constraint host_ifaces_state_chk check (state in ('up', 'down', 'unknown'));

            alter table host_ip_addresses add constraint host_ips_scope_chk check (scope in ('public', 'private', 'loopback', 'link_local', 'unspecified'));

            alter table host_mounts add constraint host_mounts_usage_chk check (used_bytes <= capacity_bytes);
            alter table host_mounts add constraint host_mounts_path_chk check (mount_point ~ '^/' and position('..' in mount_point) = 0);

            alter table host_ports add constraint host_ports_protocol_chk check (protocol in ('tcp', 'udp'));
            alter table host_ports add constraint host_ports_port_chk check (port between 1 and 65535);

            alter table host_packages add constraint host_packages_name_chk check (name ~ '^[a-z0-9][a-z0-9+.-]{0,127}$' and position(E'\n' in version) = 0);

            alter table host_ssh_keys add constraint host_ssh_keys_account_chk check (linux_account ~ '^[a-z_][a-z0-9_-]{0,31}$' and linux_account <> 'root');
            alter table host_ssh_keys add constraint host_ssh_keys_public_chk check (rahandaz_openssh_public_key(public_key));
            alter table host_ssh_keys add constraint host_ssh_keys_fp_chk check (fingerprint ~ '^SHA256:[A-Za-z0-9+/]{43}$');
            create unique index host_ssh_keys_fp_uidx on host_ssh_keys (local_host_id, fingerprint) where revoked_at is null;

            alter table firewall_rules add constraint firewall_rules_direction_chk check (direction in ('in', 'out'));
            alter table firewall_rules add constraint firewall_rules_protocol_chk check (protocol in ('tcp', 'udp', 'icmp', 'any'));
            alter table firewall_rules add constraint firewall_rules_ports_chk check (
                (protocol in ('icmp', 'any') and port_start is null and port_end is null)
                or (protocol in ('tcp', 'udp') and port_start between 1 and 65535 and (port_end is null or port_end between port_start and 65535))
            );
            alter table firewall_rules add constraint firewall_rules_origin_chk check (origin in ('system_discovered', 'panel_managed'));
            alter table firewall_rules add constraint firewall_rules_state_chk check (state in ('desired', 'observed', 'applied', 'disabled'));

            create index host_facts_cursor_idx on host_facts (local_host_id, observed_at desc, id desc);

            create trigger local_hosts_protect before delete on local_hosts
                for each row execute function rahandaz_reject_protected_delete();
            create trigger firewall_rules_protect before delete on firewall_rules
                for each row execute function rahandaz_reject_protected_delete();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('firewall_rules');
        Schema::dropIfExists('host_ssh_keys');
        Schema::dropIfExists('host_packages');
        Schema::dropIfExists('host_ports');
        Schema::dropIfExists('host_mounts');
        Schema::dropIfExists('host_disks');
        Schema::dropIfExists('host_ip_addresses');
        Schema::dropIfExists('host_network_interfaces');
        Schema::dropIfExists('host_facts');
        Schema::dropIfExists('local_hosts');
    }
};
