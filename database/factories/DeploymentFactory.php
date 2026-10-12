<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Projects\Enums\DeploymentStatus;
use App\Domain\Projects\Enums\DeploymentTrigger;
use App\Domain\Projects\Enums\PhaseStatus;
use App\Domain\Projects\Enums\ProjectSourceType;
use App\Domain\Projects\Enums\RollbackStatus;
use App\Models\Deployment;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deployment>
 */
class DeploymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'commit_sha' => str_repeat('ab', 20),
            'source_type' => ProjectSourceType::Git,
            'trigger_type' => DeploymentTrigger::Manual,
            'status' => DeploymentStatus::Queued,
            'build_status' => PhaseStatus::Pending,
            'health_status' => PhaseStatus::Pending,
            'rollback_status' => RollbackStatus::None,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Deployment $deployment): void {
            if ($deployment->environment_id === null) {
                $environment = Environment::factory()->create(
                    $deployment->workspace_id === null ? [] : ['workspace_id' => $deployment->workspace_id],
                );
                $deployment->environment_id = $environment->id;
                $deployment->workspace_id = $environment->workspace_id;
            }

            $environment = Environment::query()->findOrFail($deployment->environment_id);
            $deployment->workspace_id ??= $environment->workspace_id;
            $deployment->actor_id ??= User::factory()->create()->id;
            $deployment->plan_hash ??= hash('sha256', $deployment->environment_id.'|'.$deployment->commit_sha);
        });
    }
}
