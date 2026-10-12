<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Projects\Enums\DeploymentStrategy;
use App\Domain\Projects\Enums\EnvironmentStatus;
use App\Models\Environment;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Environment>
 */
class EnvironmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Production',
            'slug' => 'production',
            'branch_name' => 'main',
            'deployment_strategy' => DeploymentStrategy::SymlinkRelease,
            'healthcheck_path' => '/health',
            'status' => EnvironmentStatus::Draft,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Environment $environment): void {
            if ($environment->project_id === null) {
                $project = Project::factory()->create(
                    $environment->workspace_id === null ? [] : ['workspace_id' => $environment->workspace_id],
                );
                $environment->project_id = $project->id;
                $environment->workspace_id = $project->workspace_id;
                $environment->local_host_id ??= $project->local_host_id;
            }

            $project = Project::query()->findOrFail($environment->project_id);
            $environment->workspace_id ??= $project->workspace_id;
            $environment->local_host_id ??= $project->local_host_id;
            $environment->slug ??= 'production';
            $environment->name ??= $environment->slug;
            $environment->branch_name ??= 'main';

            $base = '/srv/rahandaz/projects/'.$project->slug.'/'.$environment->slug;
            $environment->release_path ??= $base.'/releases';
            $environment->shared_path ??= $base.'/shared';
        });
    }
}
