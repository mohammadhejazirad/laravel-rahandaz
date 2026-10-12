<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\RecordOperation;
use App\Domain\Projects\DeploymentRecorder;
use App\Models\Environment;
use App\Models\LocalHost;
use App\Models\OperationJob;
use App\Models\Project;
use App\Support\Identifiers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Concerns\AssertsPostgresConstraints;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class IdentityArchitectureTest extends TestCase
{
    use AssertsPostgresConstraints, CreatesPanelAccount, RefreshDatabase;

    public function test_project_graph_uses_bigint_relations_and_resolves_by_public_uuid(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id]);
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'local_host_id' => $host->id,
            'created_by' => $owner->id,
        ]);
        $environment = Environment::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $workspace->id,
            'local_host_id' => $host->id,
        ]);
        $deployment = app(DeploymentRecorder::class)->queue(
            $environment,
            $owner,
            str_repeat('ab', 20),
        );
        $job = app(RecordOperation::class)->record(new RecordOperationData(
            workspaceId: $workspace->id,
            actorId: $owner->id,
            type: 'deploy.queue',
            idempotencyKey: 'identity-deploy',
            plan: ['target' => 'none'],
            mutatesHost: false,
        ));
        $deployment->operation_job_id = $job->id;
        $deployment->save();

        $deployment->refresh();
        $this->assertSame((string) $environment->id, (string) $deployment->environment_id);
        $this->assertSame((string) $project->id, (string) $environment->project_id);
        $this->assertSame((string) $workspace->id, (string) $project->workspace_id);
        $this->assertSame((string) $job->id, (string) $deployment->operation_job_id);
        $this->assertInstanceOf(Environment::class, $deployment->environment);
        $this->assertInstanceOf(Project::class, $deployment->environment->project);
        $this->assertInstanceOf(OperationJob::class, $deployment->operationJob);
        $this->assertTrue($deployment->environment->is($environment));
        $this->assertTrue($deployment->operationJob->is($job));

        foreach ([$workspace, $project, $environment, $deployment, $job, $owner] as $model) {
            $this->assertTrue(Identifiers::isUuidV7((string) $model->uuid));
            $this->assertGreaterThan(0, (int) $model->id);
        }

        $this->assertSame('bigint', $this->columnType('projects', 'id'));
        $this->assertSame('uuid', $this->columnType('projects', 'uuid'));
        $this->assertSame('bigint', $this->columnType('environments', 'project_id'));
        $this->assertSame('bigint', $this->columnType('deployments', 'environment_id'));
        $this->assertSame('bigint', $this->columnType('deployments', 'operation_job_id'));
        $this->assertSame('bigint', $this->columnType('operation_job_steps', 'operation_job_id'));
        $this->assertSame('bigint', $this->columnType('operation_job_logs', 'operation_job_id'));
        $this->assertSame('NO', $this->columnNullable('projects', 'uuid'));
        $this->assertSame('bigint', $this->columnType('sessions', 'user_id'));
        $this->assertSame('character varying', $this->columnType('failed_jobs', 'uuid'));

        $original = $project->uuid;
        $project->name = $project->name.' ۲';
        $project->save();
        $this->assertSame($original, $project->fresh()->uuid);

        try {
            $project->uuid = Identifiers::uuid7();
            $project->save();
            $this->fail('A stored public uuid was changed.');
        } catch (InvalidArgumentException) {
            $project->refresh();
            $this->assertSame($original, $project->uuid);
        }

        $this->assertSqlstate('23514', fn () => DB::table('projects')->where('id', $project->id)->update([
            'uuid' => Identifiers::uuid7(),
        ]));

        $response = $this->actingAs($owner)->getJson(route('projects.show', $project));
        $response->assertOk()
            ->assertJsonPath('uuid', $project->uuid)
            ->assertJsonPath('workspace_uuid', $workspace->uuid)
            ->assertJsonMissingPath('id');
        $this->assertStringNotContainsString('"id":'.$project->id, $response->getContent() ?: '');

        $this->actingAs($owner)->getJson(route('projects.show', ['project' => (string) $project->id]))->assertNotFound();

        [$other] = $this->panelAccount(MembershipRole::Owner, setupComplete: true);
        $this->flushSession();
        $this->actingAs($other)->getJson(route('projects.show', $project))->assertForbidden();
    }

    private function columnType(string $table, string $column): string
    {
        $type = DB::scalar(
            'select data_type from information_schema.columns where table_schema = current_schema() and table_name = ? and column_name = ?',
            [$table, $column],
        );

        return (string) $type;
    }

    private function columnNullable(string $table, string $column): string
    {
        $nullable = DB::scalar(
            'select is_nullable from information_schema.columns where table_schema = current_schema() and table_name = ? and column_name = ?',
            [$table, $column],
        );

        return (string) $nullable;
    }
}
