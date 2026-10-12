<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Projects\Enums\MigrationPolicy;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Enums\ProjectType;
use App\Models\LocalHost;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'برنامه',
            'slug' => 'app'.bin2hex(random_bytes(4)),
            'declared_type' => ProjectType::Laravel,
            'default_branch' => 'main',
            'auto_deploy' => false,
            'zero_downtime_requested' => false,
            'migration_policy' => MigrationPolicy::ExpandOnly,
            'release_retention' => 5,
            'healthcheck_path' => '/health',
            'status' => ProjectStatus::Draft,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Project $project): void {
            if ($project->local_host_id !== null && $project->workspace_id === null) {
                $project->workspace_id = LocalHost::query()->findOrFail($project->local_host_id)->workspace_id;
            }

            if ($project->workspace_id === null) {
                $owner = User::factory()->create();
                $project->workspace_id = Workspace::factory()->create(['owner_id' => $owner->id])->id;
            }

            if ($project->local_host_id !== null) {
                return;
            }

            $host = LocalHost::query()
                ->where('workspace_id', $project->workspace_id)
                ->where('slug', 'local-host')
                ->first();

            $project->local_host_id = ($host ?? LocalHost::factory()->create([
                'workspace_id' => $project->workspace_id,
                'slug' => 'local-host',
            ]))->id;
        });
    }
}
