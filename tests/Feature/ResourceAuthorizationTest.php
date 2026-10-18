<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Access\Enums\GrantPermission;
use App\Domain\Access\OperationAccess;
use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Databases\DatabaseRegistrar;
use App\Domain\Edge\DomainRegistrar;
use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\OperationRunner;
use App\Domain\Jobs\RecordOperation;
use App\Domain\Projects\DeploymentWorkflow;
use App\Models\Backup;
use App\Models\DatabaseInstance;
use App\Models\Environment;
use App\Models\LocalHost;
use App\Models\Membership;
use App\Models\OperationJob;
use App\Models\Project;
use App\Models\ResourceGrant;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class ResourceAuthorizationTest extends TestCase
{
    use CreatesPanelAccount, RefreshDatabase;

    private function project(User $owner, Workspace $workspace): Project
    {
        $host = LocalHost::query()->forWorkspace($workspace->id)->first()
            ?? LocalHost::factory()->create(['workspace_id' => $workspace->id]);

        return Project::factory()->create(['workspace_id' => $workspace->id, 'local_host_id' => $host->id, 'created_by' => $owner->id]);
    }

    private function member(Workspace $workspace, MembershipRole $role): User
    {
        $user = User::factory()->create();
        UserProfile::factory()->create(['user_id' => $user->id, 'force_password_change' => false]);
        Membership::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function grant(User $user, Project $project, GrantPermission $permission): ResourceGrant
    {
        return ResourceGrant::factory()->create([
            'workspace_id' => $project->workspace_id,
            'membership_id' => Membership::query()->where('workspace_id', $project->workspace_id)->where('user_id', $user->id)->sole()->id,
            'resource_type' => 'project', 'resource_id' => $project->id, 'permission' => $permission,
        ]);
    }

    public static function rolesAndGrants(): array
    {
        return [
            'admin' => [MembershipRole::Admin, null, true, true],
            'operator without grant' => [MembershipRole::Operator, null, false, false],
            'operator view' => [MembershipRole::Operator, GrantPermission::View, true, false],
            'operator deploy' => [MembershipRole::Operator, GrantPermission::Deploy, true, true],
            'operator operate' => [MembershipRole::Operator, GrantPermission::Operate, true, false],
            'secret grant is independent' => [MembershipRole::Operator, GrantPermission::ManageSecrets, false, false],
            'restore grant is independent' => [MembershipRole::Operator, GrantPermission::Restore, false, false],
            'viewer without grant' => [MembershipRole::Viewer, null, false, false],
            'viewer view' => [MembershipRole::Viewer, GrantPermission::View, true, false],
            'viewer cannot deploy even with grant' => [MembershipRole::Viewer, GrantPermission::Deploy, true, false],
        ];
    }

    #[DataProvider('rolesAndGrants')]
    public function test_project_policy_requires_both_role_and_resource_permission(MembershipRole $role, ?GrantPermission $permission, bool $view, bool $deploy): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $project = $this->project($owner, $workspace);
        $user = $this->member($workspace, $role);
        if ($permission !== null) {
            $this->grant($user, $project, $permission);
        }

        $this->assertSame($view, $user->can('view', $project));
        $this->assertSame($deploy, $user->can('deploy', $project));
        $this->actingAs($user)->getJson(route('projects.show', $project))->assertStatus($view ? 200 : 403);
    }

    public function test_lists_search_pagination_and_dashboard_do_not_leak_ungranted_projects_or_jobs(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $allowed = $this->project($owner, $workspace);
        $user = $this->member($workspace, MembershipRole::Operator);
        $this->grant($user, $allowed, GrantPermission::View);
        // Newer unauthorized records must not push the authorized row off page one.
        for ($i = 0; $i < 21; $i++) {
            $this->project($owner, $workspace);
        }
        app(RecordOperation::class)->record(new RecordOperationData(workspaceId: $workspace->id, actorId: $owner->id, type: 'host.noop', idempotencyKey: 'private-job', plan: [], mutatesHost: false));

        $this->actingAs($user)->get(route('projects.index'))->assertInertia(fn (Assert $p) => $p
            ->has('projects.data', 1)->where('projects.data.0.uuid', $allowed->uuid)->where('projects.meta.total', 1));
        $this->get(route('projects.index', ['q' => $allowed->slug]))->assertInertia(fn (Assert $p) => $p->has('projects.data', 1));
        $this->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p
            ->where('inventory.projects', 1)->has('recent_projects', 1)->has('recent_jobs', 0)->where('operations.queued', 0));
        $this->get(route('jobs.index'))->assertInertia(fn (Assert $p) => $p->has('jobs.data', 0)->where('jobs.meta.total', 0));
    }

    public function test_deployment_and_domain_lists_details_and_form_options_use_project_scope(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $allowed = $this->project($owner, $workspace);
        $hidden = $this->project($owner, $workspace);
        $a = Environment::factory()->create(['project_id' => $allowed->id]);
        $b = Environment::factory()->create(['project_id' => $hidden->id]);
        $workflow = app(DeploymentWorkflow::class);
        $visible = $workflow->queueManual($a, $owner, str_repeat('a', 40), null, null);
        $private = $workflow->queueManual($b, $owner, str_repeat('b', 40), null, null);
        $registrar = app(DomainRegistrar::class);
        $registrar->register($a, $owner, 'allowed.example.org', null, null);
        $privateDomain = $registrar->register($b, $owner, 'private.example.org', null, null);
        $operator = $this->member($workspace, MembershipRole::Operator);
        $grant = $this->grant($operator, $allowed, GrantPermission::View);

        $this->actingAs($operator)->get(route('deployments.index'))->assertInertia(fn (Assert $p) => $p
            ->has('deployments.data', 1)->where('deployments.data.0.uuid', $visible->deployment->uuid)->has('environments', 0)->where('can_operate', false));
        $this->get(route('domains.index'))->assertInertia(fn (Assert $p) => $p
            ->has('domains.data', 1)->has('environments', 0)->where('can_operate', false));
        $this->get(route('deployments.show', $private->deployment))->assertForbidden();
        $this->get(route('domains.show', $privateDomain))->assertForbidden();
        $this->post(route('deployments.action', [$private->deployment, 'cancel']))->assertForbidden();
        $this->post(route('domains.certificate', $privateDomain))->assertForbidden();
        $this->post(route('deployments.store'), ['environment_uuid' => $b->uuid, 'commit_sha' => str_repeat('c', 40), 'idempotency_key' => 'idor-project'])->assertForbidden();

        $grant->update(['permission' => GrantPermission::Deploy]);
        $this->get(route('deployments.index'))->assertInertia(fn (Assert $p) => $p
            ->has('environments', 1)->where('environments.0.uuid', $a->uuid)->where('can_operate', true));
    }

    public function test_project_grant_never_grants_host_jobs_logs_backups_or_foreign_workspace_access(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $project = $this->project($owner, $workspace);
        $operator = $this->member($workspace, MembershipRole::Operator);
        $this->grant($operator, $project, GrantPermission::Deploy);
        $job = app(RecordOperation::class)->record(new RecordOperationData(workspaceId: $workspace->id, actorId: $operator->id, type: 'host.noop', idempotencyKey: 'actor-not-permission', plan: [], mutatesHost: false));
        $job->logs()->create(['sequence' => 1, 'level' => 'info', 'message' => 'private-host-log', 'recorded_at' => now()]);
        $this->assertFalse(app(OperationAccess::class)->mayExecute($job));
        $this->actingAs($operator)->get(route('jobs.show', $job))->assertForbidden()->assertDontSee('private-host-log');
        $this->get(route('backups.index'))->assertForbidden();
        $this->get(route('backups.schedules'))->assertForbidden();
        [$foreign, $otherWorkspace] = $this->panelAccount(setupComplete: true);
        $otherProject = $this->project($foreign, $otherWorkspace);
        $this->assertFalse($operator->can('view', $otherProject));
        $this->getJson(route('projects.show', $otherProject))->assertForbidden();
        $this->flushSession();
        $this->actingAs($foreign)->get(route('jobs.show', $job))->assertForbidden();
    }

    public static function revokedAccess(): array
    {
        return ['grant removed' => ['grant'], 'membership removed' => ['membership'], 'account suspended' => ['suspended'], 'role demoted' => ['role'], 'grant removed before retry' => ['retry']];
    }

    #[DataProvider('revokedAccess')]
    public function test_worker_rechecks_current_authority_and_releases_domain_lock(string $revocation): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $project = $this->project($owner, $workspace);
        $environment = Environment::factory()->create(['project_id' => $project->id]);
        $operator = $this->member($workspace, MembershipRole::Operator);
        $grant = $this->grant($operator, $project, GrantPermission::Deploy);
        $queued = app(DeploymentWorkflow::class)->queueManual($environment, $operator, str_repeat('d', 40), null, null);
        // Simulate a previously loaded actor relationship in a long-lived process.
        $queued->operation->load('actor');
        if ($revocation === 'retry') {
            $queued->operation->update(['status' => 'waiting_retry']);
        }
        match ($revocation) {
            'grant', 'retry' => $grant->delete(),
            'membership' => Membership::query()->where('user_id', $operator->id)->delete(),
            'suspended' => User::query()->whereKey($operator->id)->update(['status' => 'suspended']),
            'role' => Membership::query()->where('user_id', $operator->id)->update(['role' => 'viewer']),
        };
        $this->assertFalse(app(OperationAccess::class)->mayExecute($queued->operation));
        app(OperationRunner::class)->run($queued->operation->id);
        app(OperationRunner::class)->run($queued->operation->id);
        $job = $queued->operation->fresh();
        $this->assertSame('failed', $job->status->value);
        $this->assertSame('AUTHORIZATION_REVOKED', $job->error_code);
        $this->assertSame(1, $job->attempt);
        $this->assertSame('failed', $queued->deployment->fresh()->status->value);
        $this->assertSame('AUTHORIZATION_REVOKED', $queued->deployment->fresh()->failure_code);
        $this->assertDatabaseHas('audit_events', ['action' => 'operation.failed', 'operation_job_id' => $job->id]);
        $this->assertDatabaseCount('operation_job_logs', 1);
        // The failed request no longer holds the environment mutation lock.
        $next = app(DeploymentWorkflow::class)->queueManual($environment, $owner, str_repeat('e', 40), null, null);
        $this->assertNotSame($queued->deployment->id, $next->deployment->id);
    }

    public function test_authorized_operator_reaches_closed_gate_and_cannot_bypass_domain_authorization(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $project = $this->project($owner, $workspace);
        $environment = Environment::factory()->create(['project_id' => $project->id]);
        $operator = $this->member($workspace, MembershipRole::Operator);
        $grant = $this->grant($operator, $project, GrantPermission::Deploy);
        $queued = app(DeploymentWorkflow::class)->queueManual($environment, $operator, str_repeat('f', 40), null, null);
        app(OperationRunner::class)->run($queued->operation->id);
        $this->assertSame('EXECUTION_DISABLED', $queued->operation->fresh()->error_code);
        $grant->delete();

        $this->expectException(AuthorizationException::class);
        app(DeploymentWorkflow::class)->queueManual($environment, $operator, str_repeat('a', 40), null, null);
    }

    public function test_database_metadata_and_instance_options_do_not_leak_to_other_projects(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $project = $this->project($owner, $workspace);
        $hidden = $this->project($owner, $workspace);
        $a = Environment::factory()->create(['project_id' => $project->id]);
        $b = Environment::factory()->create(['project_id' => $hidden->id]);
        $instance = DatabaseInstance::query()->create([
            'workspace_id' => $workspace->id, 'local_host_id' => $a->local_host_id, 'engine' => 'postgresql',
            'version' => '16', 'connection_mode' => 'tcp', 'endpoint_address' => '127.0.0.1', 'port' => 5432,
            'bind_scope' => 'loopback', 'ownership' => 'project_host', 'observed_state' => 'unknown',
        ]);
        $visible = app(DatabaseRegistrar::class)->createDatabase($instance, $a, $owner, 'allowed_db', 'allowed_user', 'fixture-allowed-db-password', null, null);
        $private = app(DatabaseRegistrar::class)->createDatabase($instance, $b, $owner, 'hidden_db', 'hidden_user', 'fixture-hidden-db-password', null, null);
        $operator = $this->member($workspace, MembershipRole::Operator);
        $this->grant($operator, $project, GrantPermission::View);
        $this->actingAs($operator)->get(route('databases.index'))->assertInertia(fn (Assert $p) => $p
            ->has('databases.data', 1)->where('databases.data.0.uuid', $visible->uuid)->has('instances', 0));
        $this->get(route('databases.show', $visible))->assertOk();
        $this->get(route('databases.show', $private))->assertForbidden();
        $visible->forceFill(['protected' => true])->save();
        $this->get(route('databases.show', $visible))->assertForbidden();
        $this->get(route('databases.index'))->assertInertia(fn (Assert $p) => $p->has('databases.data', 0));
        $this->flushSession();
        $this->actingAs($owner)->get(route('databases.show', $visible))->assertOk();
    }

    public function test_backup_detail_is_not_visible_through_workspace_membership(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $project = $this->project($owner, $workspace);
        $backup = Backup::query()->create([
            'workspace_id' => $workspace->id, 'local_host_id' => $project->local_host_id, 'backup_type' => 'control_plane',
            'storage' => 'local', 'encryption_status' => 'envelope', 'state' => 'failed', 'artifact_path' => '/var/backups/rahandaz/test/missing.dump', 'retention_until' => now()->addDays(7),
        ]);
        $operator = $this->member($workspace, MembershipRole::Operator);
        $this->grant($operator, $project, GrantPermission::View);
        $this->actingAs($operator)->get(route('backups.show', $backup))->assertForbidden();
        $this->flushSession();
        $this->actingAs($owner)->get(route('backups.show', $backup))->assertOk();
    }

    public function test_environment_grant_does_not_expand_to_the_whole_project_and_archive_denial_precedes_validation(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $project = $this->project($owner, $workspace);
        $environment = Environment::factory()->create(['project_id' => $project->id]);
        $operator = $this->member($workspace, MembershipRole::Operator);
        ResourceGrant::factory()->create([
            'workspace_id' => $workspace->id,
            'membership_id' => Membership::query()->where('user_id', $operator->id)->sole()->id,
            'resource_type' => 'environment', 'resource_id' => $environment->id, 'permission' => 'deploy',
        ]);
        $this->assertFalse($operator->can('view', $project));
        $this->actingAs($operator)->post(route('projects.source-archives.store', [$project, $environment]), [])->assertForbidden();
        $this->assertDatabaseCount('project_source_archives', 0);
        $this->assertDatabaseCount('operation_jobs', 0);
    }

    public function test_json_project_detail_obeys_current_workspace_even_with_foreign_membership(): void
    {
        [$owner, $current] = $this->panelAccount(setupComplete: true);
        $owner->load('memberships');
        $this->assertSame($current->id, $owner->currentWorkspace()->id);
        [$other, $workspace] = $this->panelAccount(setupComplete: true);
        $foreign = $this->project($other, $workspace);
        Membership::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'admin']);
        $this->assertTrue($owner->can('view', $foreign));
        $this->actingAs($owner)->getJson(route('projects.show', $foreign))->assertNotFound();
    }

    public function test_domain_service_cannot_bypass_http_resource_authorization(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $project = $this->project($owner, $workspace);
        $environment = Environment::factory()->create(['project_id' => $project->id]);
        $operator = $this->member($workspace, MembershipRole::Operator);
        try {
            app(DomainRegistrar::class)->register($environment, $operator, 'blocked.example.org', null, null);
            $this->fail('Domain service accepted an ungranted actor.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('domains', 0);
            $this->assertDatabaseCount('operation_jobs', 0);
        }
    }

    public function test_worker_rejects_an_unbound_resource_or_unknown_operation_despite_project_grant(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $project = $this->project($owner, $workspace);
        $environment = Environment::factory()->create(['project_id' => $project->id]);
        $operator = $this->member($workspace, MembershipRole::Operator);
        $this->grant($operator, $project, GrantPermission::Deploy);
        foreach ([['deploy.prepare', 'project', $project->id], ['nginx.raw.apply', 'environment', $environment->id], ['deploy.prepare', 'environment', null]] as $i => [$type, $resourceType, $resourceId]) {
            $job = app(RecordOperation::class)->record(new RecordOperationData(
                workspaceId: $workspace->id, actorId: $operator->id, type: $type, idempotencyKey: 'unbound-'.$i,
                plan: [], mutatesHost: true, resourceType: $resourceType, resourceId: $resourceId,
            ));
            $this->assertFalse(app(OperationAccess::class)->mayExecute($job));
        }
    }

    public function test_domain_job_uses_current_project_permission_in_the_worker(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $project = $this->project($owner, $workspace);
        $environment = Environment::factory()->create(['project_id' => $project->id]);
        $operator = $this->member($workspace, MembershipRole::Operator);
        $grant = $this->grant($operator, $project, GrantPermission::Deploy);
        app(DomainRegistrar::class)->register($environment, $operator, 'operator.example.org', null, null);
        $job = OperationJob::query()->where('type', 'nginx.site.apply')->sole();
        $this->assertTrue(app(OperationAccess::class)->mayExecute($job));
        $grant->delete();
        app(OperationRunner::class)->run($job->id);
        $this->assertSame('AUTHORIZATION_REVOKED', $job->fresh()->error_code);
    }
}
