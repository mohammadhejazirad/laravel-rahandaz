<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Projects\DeploymentRecorder;
use App\Domain\Projects\Enums\DeploymentStatus;
use App\Domain\Projects\Enums\DeploymentTrigger;
use App\Domain\Projects\Enums\PhaseStatus;
use App\Domain\Projects\Exceptions\DeploymentLockConflict;
use App\Models\Deployment;
use App\Models\Environment;
use App\Models\LocalHost;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\AssertsPostgresConstraints;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class DeploymentSchemaTest extends TestCase
{
    use AssertsPostgresConstraints, CreatesPanelAccount, RefreshDatabase;

    public function test_one_mutating_deployment_locks_an_environment_until_it_finishes(): void
    {
        [$user, $workspace] = $this->panelAccount();
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id]);
        $project = Project::factory()->create([
            'workspace_id' => $workspace->id,
            'local_host_id' => $host->id,
            'created_by' => $user->id,
        ]);
        $environment = $this->environmentFor($project, 'production', 'main');
        $beta = $this->environmentFor($project, 'beta', 'beta');
        $commit = str_repeat('ab', 20);
        $recorder = app(DeploymentRecorder::class);

        $first = $recorder->queue($environment, $user, $commit);
        $this->assertSame(DeploymentStatus::Queued, $first->status);
        $this->assertSame($commit, $first->commit_sha);
        $this->assertTrue($environment->deployments()->whereKey($first->id)->exists());

        try {
            $recorder->queue($environment, $user, $commit);
            $this->fail('A second mutating deployment was stored.');
        } catch (DeploymentLockConflict) {
            $this->assertSame(1, Deployment::query()->where('environment_id', $environment->id)->count());
        }

        $betaDeployment = $recorder->queue($beta, $user, str_repeat('cd', 20));
        $this->assertNotSame($first->id, $betaDeployment->id);

        $first->status = DeploymentStatus::Failed;
        $first->failure_code = 'EXECUTION_DISABLED';
        $first->save();

        $second = $recorder->queue($environment, $user, str_repeat('cd', 20));
        $this->assertSame(DeploymentStatus::Queued, $second->status);

        $second->status = DeploymentStatus::Succeeded;
        $this->assertSqlstate('23514', fn () => $second->save());

        $second->forceFill([
            'status' => DeploymentStatus::Succeeded,
            'build_status' => PhaseStatus::Succeeded,
            'health_status' => PhaseStatus::Succeeded,
        ])->save();
        $this->assertSame(DeploymentStatus::Succeeded, $second->fresh()->status);

        $second->commit_sha = str_repeat('ef', 20);
        $this->assertSqlstate('23514', fn () => $second->save());
        $this->assertSame(str_repeat('cd', 20), $second->fresh()->commit_sha);

        $this->expectException(InvalidArgumentException::class);
        $recorder->queue($environment, $user, $commit, DeploymentTrigger::Rollback);
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
}
