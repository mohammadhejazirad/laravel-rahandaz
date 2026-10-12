<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Access\Enums\GrantPermission;
use App\Domain\Access\Enums\GrantResourceType;
use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Databases\Enums\DatabaseEngine;
use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\Exceptions\UnsafePlan;
use App\Domain\Jobs\RecordOperation;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Settings\Enums\SettingScope;
use App\Domain\Settings\Enums\SettingValueType;
use App\Models\ApplicationSetting;
use App\Models\Backup;
use App\Models\Credential;
use App\Models\DatabaseInstance;
use App\Models\DatabaseUser;
use App\Models\DatabaseUserGrant;
use App\Models\Deployment;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\HostFact;
use App\Models\HostSshKey;
use App\Models\LocalHost;
use App\Models\Membership;
use App\Models\NginxSite;
use App\Models\OperationJobLog;
use App\Models\PanelNotification;
use App\Models\Project;
use App\Models\ProjectDatabase;
use App\Models\ResourceGrant;
use App\Models\Schedule;
use App\Models\ServerPolicy;
use App\Models\SetupSession;
use App\Models\User;
use App\Support\Identifiers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Concerns\AssertsPostgresConstraints;
use Tests\Concerns\CreatesPanelAccount;
use Tests\Support\OpenSshFixture;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use AssertsPostgresConstraints, CreatesPanelAccount, RefreshDatabase;

    public function test_domain_rows_use_uuid_v7_and_reject_client_v4_keys(): void
    {
        [$user, $workspace] = $this->panelAccount();
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id]);
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'local_host_id' => $host->id,
            'created_by' => $user->id,
        ]);

        $this->assertTrue(Identifiers::isUuidV7($user->id));
        $this->assertTrue(Identifiers::isUuidV7($host->id));
        $this->assertTrue(Identifiers::isUuidV7($project->id));
        $this->assertNotContains('id', $project->getFillable());
        $this->assertNotContains('protected', $host->getFillable());
        $this->assertFalse(Schema::hasColumn('users', 'deleted_at'));
        $this->assertTrue(Schema::hasColumns('users', ['last_login_at', 'last_activity_at']));

        $this->expectException(InvalidArgumentException::class);
        User::factory()->create(['id' => (string) Str::uuid()]);
    }

    public function test_project_graph_keeps_workspace_scope_and_restricts_history(): void
    {
        [$user, $workspace] = $this->panelAccount();
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id]);
        $fact = HostFact::factory()->create(['local_host_id' => $host->id]);
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'local_host_id' => $host->id,
            'created_by' => $user->id,
        ]);
        $production = $this->environmentFor($project, 'production', 'main');
        $beta = $this->environmentFor($project, 'beta', 'beta');

        $this->assertTrue($workspace->projects()->whereKey($project->id)->exists());
        $this->assertTrue($workspace->localHost()->whereKey($host->id)->exists());
        $this->assertSame($host->id, $fact->local_host_id);
        $this->assertCount(2, $project->environments);
        $this->assertSame('beta', $beta->branch_name);
        $this->assertNotSame($production->release_path, $production->shared_path);

        EnvironmentVariable::query()->create([
            'environment_id' => $production->id,
            'workspace_id' => $workspace->id,
            'key' => 'APP_NAME',
            'is_secret' => false,
            'value' => 'Rahandaz',
        ]);
        $this->assertSqlstate('23514', fn () => EnvironmentVariable::query()->create([
            'environment_id' => $production->id,
            'workspace_id' => $workspace->id,
            'key' => 'APP_SECRET',
            'is_secret' => true,
            'value' => 'plain-text',
        ]));

        $credential = Credential::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'env',
        ]);
        $secret = EnvironmentVariable::query()->create([
            'environment_id' => $production->id,
            'workspace_id' => $workspace->id,
            'key' => 'DB_PASSWORD',
            'is_secret' => true,
            'credential_id' => $credential->id,
        ]);
        $this->assertNull($secret->value);
        $this->assertTrue($production->variables()->whereKey($secret->id)->exists());

        $this->assertSqlstate('23503', fn () => $project->delete());
        $this->assertNotNull($production->fresh());

        $project->forceFill(['protected' => true])->save();
        $this->assertSqlstate('42501', fn () => Project::query()->whereKey($project->id)->delete());
    }

    public function test_database_names_grants_and_backups_stay_constrained(): void
    {
        [$user, $workspace] = $this->panelAccount();
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id]);
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'local_host_id' => $host->id,
        ]);
        $environment = $this->environmentFor($project, 'production', 'main');
        $instance = $this->databaseInstance($host);
        $databaseUser = $this->databaseUser($instance, $user, 'app_user');
        $database = ProjectDatabase::query()->create([
            'database_instance_id' => $instance->id,
            'environment_id' => $environment->id,
            'workspace_id' => $workspace->id,
            'name' => 'app_main',
            'owner_database_user_id' => $databaseUser->id,
            'status' => 'ready',
        ]);
        $grant = DatabaseUserGrant::query()->create([
            'database_instance_id' => $instance->id,
            'database_user_id' => $databaseUser->id,
            'project_database_id' => $database->id,
            'privilege' => 'read',
        ]);

        $this->assertTrue($databaseUser->grants()->whereKey($grant->id)->exists());
        $this->assertTrue($database->grants()->whereKey($grant->id)->exists());
        $this->assertTrue($instance->projectDatabases()->whereKey($database->id)->exists());

        $second = $this->environmentFor($project, 'staging', 'develop');
        ProjectDatabase::query()->create([
            'database_instance_id' => $instance->id,
            'environment_id' => $second->id,
            'workspace_id' => $workspace->id,
            'name' => 'app_stage',
            'owner_database_user_id' => $databaseUser->id,
            'status' => 'requested',
        ]);

        $this->assertSqlstate('23505', fn () => ProjectDatabase::query()->create([
            'database_instance_id' => $instance->id,
            'environment_id' => $second->id,
            'workspace_id' => $workspace->id,
            'name' => 'app_main',
            'owner_database_user_id' => $databaseUser->id,
        ]));
        $this->assertSqlstate('23514', fn () => $this->databaseUser($instance, $user, 'postgres'));
        $this->assertSqlstate('23514', fn () => DatabaseUser::query()->create([
            'database_instance_id' => $instance->id,
            'workspace_id' => $workspace->id,
            'username' => 'other_user',
            'credential_id' => $databaseUser->credential_id,
            'auth_method' => 'mysql_native',
        ]));

        $backup = Backup::query()->create([
            'workspace_id' => $workspace->id,
            'local_host_id' => $host->id,
            'project_database_id' => $database->id,
            'backup_type' => 'database',
            'storage' => 'local',
            'artifact_path' => '/var/backups/rahandaz/db/app_main.dump',
            'checksum_sha256' => hash('sha256', 'artifact'),
            'encryption_status' => 'envelope',
            'retention_until' => now()->addDays(7),
            'state' => 'succeeded',
        ]);
        $this->assertTrue($database->backups()->whereKey($backup->id)->exists());
        $this->assertSqlstate('23514', fn () => Backup::query()->create([
            'workspace_id' => $workspace->id,
            'local_host_id' => $host->id,
            'backup_type' => 'control_plane',
            'storage' => 'local',
            'artifact_path' => '/tmp/rahandaz.dump',
            'encryption_status' => 'envelope',
            'state' => 'running',
        ]));
        $this->assertSqlstate('23514', fn () => Backup::query()->create([
            'workspace_id' => $workspace->id,
            'local_host_id' => $host->id,
            'backup_type' => 'control_plane',
            'storage' => 'local',
            'artifact_path' => '/var/backups/rahandaz/control/plane.dump',
            'encryption_status' => 'envelope',
            'state' => 'succeeded',
        ]));

        $instance->forceFill(['protected' => true])->save();
        $this->assertSqlstate('42501', fn () => $instance->delete());
    }

    public function test_audit_chain_settings_schedules_and_grants_reject_unsafe_rows(): void
    {
        [$user, $workspace] = $this->panelAccount();
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id]);
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'local_host_id' => $host->id,
        ]);
        $recorder = app(AuditRecorder::class);
        $first = $recorder->record($workspace, $user, 'auth.login', 'user', $user->id, AuditResult::Success, ['method' => 'password']);
        $second = $recorder->record($workspace, $user, 'project.create', 'project', $project->id, AuditResult::Success);
        $this->assertSame($first->event_hash, $second->prev_hash);
        $this->assertTrue($user->auditEvents()->whereKey($first->id)->exists());
        $this->assertSqlstate('42501', fn () => $first->delete());

        try {
            $recorder->record($workspace, $user, 'secret.read', 'credential', null, AuditResult::Denied, ['api_token' => 'x']);
            $this->fail('Secret metadata was stored.');
        } catch (UnsafePlan) {
            $this->assertSame(2, $workspace->auditEvents()->count());
        }

        $grant = ResourceGrant::query()->create([
            'workspace_id' => $workspace->id,
            'membership_id' => $user->currentMembership()->id,
            'resource_type' => GrantResourceType::Project,
            'resource_id' => $project->id,
            'permission' => GrantPermission::Deploy,
            'created_by' => $user->id,
        ]);
        $this->assertInstanceOf(Project::class, $grant->resource());

        [, $otherWorkspace] = $this->panelAccount();
        $otherMembership = Membership::query()->where('workspace_id', $otherWorkspace->id)->firstOrFail();
        $this->assertSqlstate('23514', fn () => ResourceGrant::query()->create([
            'workspace_id' => $otherWorkspace->id,
            'membership_id' => $otherMembership->id,
            'resource_type' => GrantResourceType::Project,
            'resource_id' => $project->id,
            'permission' => GrantPermission::View,
        ]));

        ApplicationSetting::query()->create([
            'scope' => SettingScope::Installation,
            'key' => 'ui.locale',
            'value_type' => SettingValueType::String,
            'value_text' => 'fa',
        ]);
        $this->assertSqlstate('23514', fn () => ApplicationSetting::query()->create([
            'scope' => SettingScope::Installation,
            'key' => 'backup.token',
            'value_type' => SettingValueType::String,
            'value_text' => 'hello',
        ]));

        SetupSession::query()->create([
            'workspace_id' => $workspace->id,
            'owner_id' => $user->id,
            'mode' => 'manual',
            'answers' => ['locale' => 'fa'],
            'state' => 'in_progress',
            'started_at' => now(),
        ]);
        $this->assertSqlstate('23505', fn () => SetupSession::query()->create([
            'workspace_id' => $workspace->id,
            'owner_id' => $user->id,
            'mode' => 'manual',
            'answers' => ['locale' => 'en'],
            'state' => 'in_progress',
            'started_at' => now(),
        ]));
        try {
            SetupSession::query()->create([
                'workspace_id' => $otherWorkspace->id,
                'owner_id' => $user->id,
                'mode' => 'manual',
                'answers' => ['git_token' => 'x'],
                'state' => 'in_progress',
                'started_at' => now(),
            ]);
            $this->fail('Setup answers stored a token key.');
        } catch (UnsafePlan) {
            $this->assertSame(0, SetupSession::query()->where('workspace_id', $otherWorkspace->id)->count());
        }

        $this->assertFalse(Schema::hasColumn('schedules', 'command'));
        Schedule::query()->create([
            'workspace_id' => $workspace->id,
            'operation_key' => 'metric.sample',
            'timezone' => 'UTC',
            'cron_expression' => '*/15 * * * *',
            'enabled' => true,
            'overlap_policy' => 'skip',
        ]);
        $this->assertSqlstate('23514', fn () => Schedule::query()->create([
            'workspace_id' => $workspace->id,
            'operation_key' => 'shell.exec',
            'timezone' => 'UTC',
            'cron_expression' => '0 * * * *',
            'enabled' => false,
            'overlap_policy' => 'skip',
        ]));
    }

    public function test_host_edge_and_notification_rules_reject_private_material(): void
    {
        [$user, $workspace] = $this->panelAccount();
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id]);

        HostSshKey::query()->create([
            'local_host_id' => $host->id,
            'user_id' => $user->id,
            'linux_account' => 'rahandaz',
            'public_key' => OpenSshFixture::PUBLIC_KEY,
            'fingerprint' => OpenSshFixture::FINGERPRINT,
            'label' => 'owner',
            'added_by' => $user->id,
        ]);
        $this->assertSqlstate('23514', fn () => HostSshKey::query()->create([
            'local_host_id' => $host->id,
            'linux_account' => 'rahandaz',
            'public_key' => '-----BEGIN OPENSSH PRIVATE KEY-----',
            'fingerprint' => OpenSshFixture::FINGERPRINT,
            'label' => 'private',
        ]));
        $this->assertSqlstate('42501', fn () => $host->delete());

        $panel = new NginxSite([
            'workspace_id' => $workspace->id,
            'local_host_id' => $host->id,
            'site_role' => 'panel',
            'server_name' => 'panel.example.test',
        ]);
        $panel->forceFill(['protected' => true]);
        $panel->save();
        $this->assertTrue($panel->getAttribute('protected'));
        $this->assertSqlstate('23514', fn () => NginxSite::query()->create([
            'workspace_id' => $workspace->id,
            'local_host_id' => $host->id,
            'site_role' => 'panel',
            'server_name' => 'other.example.test',
        ]));
        $this->assertSqlstate('42501', fn () => $panel->delete());

        $policy = ServerPolicy::query()->create(['local_host_id' => $host->id]);
        $policy->server_tokens = true;
        $this->assertSqlstate('23514', fn () => $policy->save());

        PanelNotification::query()->create([
            'workspace_id' => $workspace->id,
            'recipient_id' => $user->id,
            'type' => 'deployment.failed',
            'level' => 'warning',
            'title' => 'استقرار',
            'body' => 'استقرار ناموفق بود.',
            'action_path' => '/rahandaz/projects',
            'event_key' => 'notice-'.bin2hex(random_bytes(4)),
        ]);
        $this->assertSqlstate('23514', fn () => PanelNotification::query()->create([
            'workspace_id' => $workspace->id,
            'recipient_id' => $user->id,
            'type' => 'deployment.failed',
            'level' => 'warning',
            'title' => 'بیرون',
            'body' => 'آدرس خارجی مجاز نیست.',
            'action_path' => 'https://evil.example',
            'event_key' => 'notice-'.bin2hex(random_bytes(4)),
        ]));
    }

    public function test_operation_logs_are_ordered_and_hot_indexes_exist(): void
    {
        [$user, $workspace] = $this->panelAccount();
        $job = app(RecordOperation::class)->record(new RecordOperationData(
            workspaceId: $workspace->id,
            actorId: $user->id,
            type: 'host.noop',
            idempotencyKey: 'schema-log',
            plan: ['target' => 'none'],
            mutatesHost: false,
        ));
        $payload = $job->outboxMessages()->firstOrFail()->payload;
        $this->assertSame(['operation_job_id' => $job->id], $payload);

        OperationJobLog::query()->create([
            'operation_job_id' => $job->id,
            'sequence' => 2,
            'level' => 'info',
            'message' => 'second',
            'recorded_at' => now(),
        ]);
        OperationJobLog::query()->create([
            'operation_job_id' => $job->id,
            'sequence' => 1,
            'level' => 'info',
            'message' => 'first',
            'recorded_at' => now(),
        ]);
        $this->assertSame(['first', 'second'], $job->logs()->orderBy('sequence')->pluck('message')->all());
        $this->assertSqlstate('23505', fn () => OperationJobLog::query()->create([
            'operation_job_id' => $job->id,
            'sequence' => 1,
            'level' => 'info',
            'message' => 'duplicate',
            'recorded_at' => now(),
        ]));

        $indexes = collect(DB::select('select indexname from pg_indexes where schemaname = current_schema()'))
            ->pluck('indexname');

        foreach ([
            'deployments_one_mutation_uidx',
            'credentials_active_blind_uidx',
            'git_deliveries_uidx',
            'releases_one_active_uidx',
            'audit_events_cursor_idx',
            'metric_samples_cursor_idx',
            'execution_leases_one_active_idx',
            'host_facts_cursor_idx',
        ] as $index) {
            $this->assertTrue($indexes->contains($index), $index);
        }

        $this->assertFalse(Schema::hasColumn('git_webhook_deliveries', 'payload'));
        $this->assertFalse(Schema::hasColumn('database_users', 'password'));
        $this->assertSame(0, Deployment::query()->count());
        $this->assertSame(ProjectStatus::Draft, Project::factory()->create([
            'workspace_id' => $workspace->id,
            'local_host_id' => $hostId = LocalHost::factory()->create(['workspace_id' => $workspace->id, 'slug' => 'probe-host'])->id,
        ])->status);
        $this->assertNotEmpty($hostId);
    }

    private function environmentFor(Project $project, string $slug, string $branch): Environment
    {
        $base = '/srv/rahandaz/projects/'.$project->slug.'/'.$slug;

        return Environment::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'local_host_id' => $project->local_host_id,
            'name' => $slug,
            'slug' => $slug,
            'branch_name' => $branch,
            'release_path' => $base.'/releases',
            'shared_path' => $base.'/shared',
        ]);
    }

    private function databaseInstance(LocalHost $host, int $port = 5432): DatabaseInstance
    {
        return DatabaseInstance::query()->create([
            'workspace_id' => $host->workspace_id,
            'local_host_id' => $host->id,
            'engine' => DatabaseEngine::Postgresql,
            'version' => '16',
            'port' => $port,
            'connection_mode' => 'tcp',
            'endpoint_address' => '127.0.0.1',
            'bind_scope' => 'loopback',
            'ownership' => 'project_host',
            'observed_state' => 'unknown',
        ]);
    }

    private function databaseUser(DatabaseInstance $instance, User $actor, string $username): DatabaseUser
    {
        $workspace = $instance->workspace;
        $credential = Credential::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $actor->id,
            'type' => 'database',
        ]);

        return DatabaseUser::query()->create([
            'database_instance_id' => $instance->id,
            'workspace_id' => $workspace->id,
            'username' => $username,
            'credential_id' => $credential->id,
            'auth_method' => 'scram_sha256',
        ]);
    }
}
