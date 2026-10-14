<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Backups\BackupWorkflow;
use App\Domain\Backups\Exceptions\BackupRejected;
use App\Domain\Jobs\OperationRunner;
use App\Domain\Monitoring\MetricBoard;
use App\Domain\Projects\DeploymentWorkflow;
use App\Domain\Projects\Enums\DeploymentStatus;
use App\Models\Backup;
use App\Models\Credential;
use App\Models\DatabaseInstance;
use App\Models\DatabaseUser;
use App\Models\Deployment;
use App\Models\Domain;
use App\Models\Environment;
use App\Models\LocalHost;
use App\Models\MetricSample;
use App\Models\OperationJob;
use App\Models\Project;
use App\Models\ProjectDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class V1FeatureWorkflowTest extends TestCase
{
    use CreatesPanelAccount, RefreshDatabase;

    private function graph(MembershipRole $role = MembershipRole::Owner): array
    {
        [$user, $workspace] = $this->panelAccount($role, setupComplete: true);
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id]);
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'local_host_id' => $host->id, 'created_by' => $user->id]);
        $env = Environment::factory()->create(['project_id' => $project->id]);

        return [$user, $workspace, $host, $env];
    }

    public function test_core_pages_read_actual_scoped_collections(): void
    {
        [$owner] = $this->graph();
        foreach (['deployments', 'domains', 'databases', 'backups', 'monitoring'] as $area) {
            $this->actingAs($owner)->get(route($area.'.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->component($area.'/index'));
        }
        $this->get(route('backups.schedules'))->assertOk();
    }

    public function test_deployment_job_failure_releases_environment_and_retry_keeps_sha(): void
    {
        [$owner, , , $env] = $this->graph();
        $sha = str_repeat('a', 40);
        $body = ['environment_uuid' => $env->uuid, 'commit_sha' => $sha, 'idempotency_key' => 'manual-deployment-1'];
        $this->actingAs($owner)->post(route('deployments.store'), $body)->assertRedirect();
        $this->post(route('deployments.store'), $body)->assertRedirect();
        $this->assertSame(1, Deployment::query()->count());
        $d = Deployment::query()->firstOrFail();
        app(OperationRunner::class)->run($d->operation_job_id);
        $this->assertSame(DeploymentStatus::Failed, $d->fresh()->status);
        $this->assertSame('EXECUTION_DISABLED', $d->fresh()->failure_code);
        $this->post(route('deployments.action', [$d, 'retry']))->assertRedirect();
        $this->assertSame(2, Deployment::query()->count());
        $this->assertSame($sha, Deployment::query()->latest('id')->value('commit_sha'));
        $this->get(route('deployments.show', $d))->assertOk()->assertInertia(fn (Assert $p) => $p->where('deployment.status', 'failed')->missing('deployment.id')->missing('deployment.plan'));
    }

    public function test_deployment_validation_scope_and_viewer_authorization(): void
    {
        [$owner, , , $env] = $this->graph();
        [$viewer] = $this->graph(MembershipRole::Viewer);
        $data = ['environment_uuid' => $env->uuid, 'commit_sha' => str_repeat('b', 40), 'idempotency_key' => 'manual-scope-1'];
        $this->actingAs($viewer)->post(route('deployments.store'), $data)->assertForbidden();
        [$foreign] = $this->graph();
        $this->flushSession();
        $this->actingAs($foreign)->post(route('deployments.store'), $data)->assertNotFound();
        $this->flushSession();
        $this->actingAs($owner)->post(route('deployments.store'), [...$data, 'commit_sha' => 'main'])->assertSessionHasErrors('commit_sha');
        $this->assertSame(0, Deployment::query()->count());
    }

    public function test_cancel_before_worker_never_executes_or_reports_success(): void
    {
        [$owner, , , $env] = $this->graph();
        $queued = app(DeploymentWorkflow::class)->queueManual($env, $owner, str_repeat('c', 40), null, null);
        $this->actingAs($owner)->post(route('deployments.action', [$queued->deployment, 'cancel']))->assertRedirect();
        app(OperationRunner::class)->run($queued->operation->id);
        $this->assertSame('cancelled', $queued->operation->fresh()->status->value);
        $this->assertSame(DeploymentStatus::Cancelled, $queued->deployment->fresh()->status);
    }

    public function test_domain_and_certificate_are_requests_not_issued_or_installed(): void
    {
        [$owner, , , $env] = $this->graph();
        $this->actingAs($owner)->post(route('domains.store'), ['environment_uuid' => $env->uuid, 'hostname' => 'app.example.org'])->assertRedirect();
        $domain = Domain::query()->firstOrFail();
        $this->assertSame('unset', $domain->validation_status);
        $this->post(route('domains.certificate', $domain))->assertRedirect();
        $domain->refresh();
        $this->assertSame('requested', $domain->certificate->install_state->value);
        $this->assertNull($domain->certificate->not_after);
        $this->get(route('domains.show', $domain))->assertInertia(fn (Assert $p) => $p->missing('domain.id')->missing('domain.certificate.credential_id'));
        $this->post(route('domains.store'), ['environment_uuid' => $env->uuid, 'hostname' => 'app.example.org'])->assertSessionHasErrors('hostname');
        $this->assertSame(1, Domain::query()->count());
    }

    public function test_backup_request_creates_job_and_never_invents_an_artifact(): void
    {
        [$owner, , $host] = $this->graph();
        $data = ['target_type' => 'local_host', 'target_uuid' => $host->uuid, 'idempotency_key' => 'backup-control-1'];
        $this->actingAs($owner)->post(route('backups.store'), $data)->assertRedirect();
        $this->post(route('backups.store'), $data)->assertRedirect();
        $this->assertSame(1, OperationJob::query()->count());
        $this->assertDatabaseCount('backups', 0);
    }

    public function test_monitoring_keeps_host_series_separate_and_marks_old_samples_stale(): void
    {
        [$owner, $workspace, $host] = $this->graph();
        $second = LocalHost::factory()->create(['workspace_id' => $workspace->id, 'slug' => 'test-host']);
        foreach ([$host, $second] as $h) {
            foreach ([4, 5] as $minutes) {
                MetricSample::query()->create(['local_host_id' => $h->id, 'source' => 'host', 'collected_at' => now()->subMinutes($minutes), 'observed_at' => now()->subMinutes($minutes), 'cpu_percent' => $h->is($host) ? 10 : 90]);
            }
        }
        $board = app(MetricBoard::class)->forWorkspace($workspace);
        $this->assertCount(2, $board['series']);
        $this->assertCount(2, $board['series'][0]['points']);
        $this->assertSame($board['series'][0]['points'][0]['cpu_percent'], $board['series'][0]['points'][1]['cpu_percent']);
        $this->assertTrue($board['samples'][0]['stale']);
        $this->actingAs($owner)->get(route('monitoring.index'))->assertOk();
    }

    public function test_database_requests_encrypt_passwords_and_select_engine_auth_without_host_success(): void
    {
        [$owner, $workspace, $host, $env] = $this->graph();
        $this->actingAs($owner);
        foreach (['postgresql' => 'scram_sha256', 'mysql' => 'mysql_native', 'mariadb' => 'mysql_native', 'mongodb' => 'mongodb_scram'] as $engine => $auth) {
            $instance = DatabaseInstance::query()->create(['workspace_id' => $workspace->id, 'local_host_id' => $host->id, 'engine' => $engine, 'version' => 'test', 'connection_mode' => 'tcp', 'endpoint_address' => '127.0.0.1', 'port' => 5400 + DatabaseInstance::query()->count(), 'bind_scope' => 'loopback', 'ownership' => 'project_host', 'observed_state' => 'unknown']);
            $password = 'Isolated-test-secret-123-'.$engine;
            $this->post(route('databases.store'), ['instance_uuid' => $instance->uuid, 'environment_uuid' => $env->uuid, 'name' => 'app_'.$engine, 'username' => 'user_'.$engine, 'password' => $password])->assertRedirect()->assertSessionHasNoErrors();
            $database = ProjectDatabase::query()->where('database_instance_id', $instance->id)->firstOrFail();
            $this->assertSame('requested', $database->status->value);
            $this->assertSame($auth, $database->ownerUser->auth_method);
            $job = OperationJob::query()->where('resource_id', $database->id)->where('type', 'database.create')->firstOrFail();
            $this->assertStringNotContainsString($password, json_encode($job->plan, JSON_THROW_ON_ERROR));
            $this->assertStringNotContainsString($password, json_encode(Credential::query()->firstOrFail()->getRawOriginal('envelope'), JSON_THROW_ON_ERROR));
            $this->get(route('databases.show', $database))->assertOk()->assertInertia(fn (Assert $p) => $p->where('database.engine', $engine)->missing('database.password')->missing('database.credential_id')->missing('database.id'));
        }
        $this->assertSame(4, DatabaseUser::query()->count());
        $this->post(route('databases.store'), ['instance_uuid' => $instance->uuid, 'environment_uuid' => $env->uuid, 'name' => 'app_duplicate', 'username' => 'user_duplicate', 'password' => $password])->assertSessionHasErrors('password');
        $this->assertNull(session('_old_input.password'));
        $this->assertDatabaseCount('project_databases', 4);
        $this->assertDatabaseCount('credentials', 4);
    }

    public function test_database_requests_reject_protected_foreign_instances_and_unsafe_names(): void
    {
        [$owner, $workspace, $host, $env] = $this->graph();
        $instance = DatabaseInstance::query()->create(['workspace_id' => $workspace->id, 'local_host_id' => $host->id, 'engine' => 'postgresql', 'version' => '16', 'connection_mode' => 'tcp', 'endpoint_address' => '127.0.0.1', 'port' => 5432, 'bind_scope' => 'loopback', 'ownership' => 'project_host', 'observed_state' => 'unknown']);
        $body = ['instance_uuid' => $instance->uuid, 'environment_uuid' => $env->uuid, 'name' => 'app_test', 'username' => 'app_user', 'password' => 'Isolated-test-secret-123'];
        $this->actingAs($owner)->post(route('databases.store'), [...$body, 'name' => 'test; DROP DATABASE postgres'])->assertSessionHasErrors('name');
        $this->post(route('databases.store'), [...$body, 'name' => 'rahandaz'])->assertSessionHasErrors('name');
        $instance->forceFill(['protected' => true])->save();
        $this->post(route('databases.store'), $body)->assertNotFound();
        [$foreign] = $this->graph();
        $this->flushSession();
        $this->actingAs($foreign)->post(route('databases.store'), $body)->assertNotFound();
        $this->assertDatabaseCount('project_databases', 0);
        $this->assertDatabaseCount('credentials', 0);
    }

    public function test_restore_metadata_cannot_approve_a_missing_real_artifact(): void
    {
        [$owner, $workspace, $host] = $this->graph();
        $backup = Backup::query()->create(['workspace_id' => $workspace->id, 'local_host_id' => $host->id, 'backup_type' => 'control_plane', 'storage' => 'local', 'artifact_path' => '/var/backups/rahandaz/test/missing.dump', 'checksum_sha256' => hash('sha256', 'fixture'), 'encryption_status' => 'envelope', 'retention_until' => now()->addDays(7), 'state' => 'succeeded', 'verified_at' => now()]);
        $workflow = app(BackupWorkflow::class);
        $restore = $workflow->requestRestore($backup, $owner, 'RESTORE', null, null);
        try {
            $workflow->approveRestore($restore, $owner, null, null);
            $this->fail('Metadata was incorrectly treated as verified recovery.');
        } catch (BackupRejected) {
            $this->assertSame('pending', $restore->fresh()->approval_state->value);
            $this->assertNull($restore->fresh()->operation_job_id);
            $this->assertDatabaseCount('operation_jobs', 0);
        }
    }
}
