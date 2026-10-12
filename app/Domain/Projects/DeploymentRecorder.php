<?php

declare(strict_types=1);

namespace App\Domain\Projects;

use App\Domain\Projects\Enums\DeploymentStatus;
use App\Domain\Projects\Enums\DeploymentTrigger;
use App\Domain\Projects\Enums\PhaseStatus;
use App\Domain\Projects\Enums\RollbackStatus;
use App\Domain\Projects\Exceptions\DeploymentLockConflict;
use App\Models\Deployment;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DeploymentRecorder
{
    public function queue(
        Environment $environment,
        User $actor,
        string $commitSha,
        DeploymentTrigger $trigger = DeploymentTrigger::Manual,
        ?string $planHash = null,
        ?Deployment $rollbackOf = null,
    ): Deployment {
        if ($trigger === DeploymentTrigger::Rollback && $rollbackOf === null) {
            throw new InvalidArgumentException('A rollback must name the deployment it reverses.');
        }

        if ($trigger !== DeploymentTrigger::Rollback && $rollbackOf !== null) {
            throw new InvalidArgumentException('Only a rollback may reference another deployment.');
        }

        $environment->loadMissing('project');
        $planHash ??= hash('sha256', $environment->id.'|'.$commitSha.'|'.$trigger->value);

        try {
            return DB::transaction(fn (): Deployment => Deployment::query()->create([
                'environment_id' => $environment->id,
                'workspace_id' => $environment->workspace_id,
                'remote_repository_id' => $environment->project?->remote_repository_id,
                'commit_sha' => $commitSha,
                'trigger_type' => $trigger,
                'actor_id' => $actor->id,
                'status' => DeploymentStatus::Queued,
                'build_status' => PhaseStatus::Pending,
                'health_status' => PhaseStatus::Pending,
                'rollback_status' => RollbackStatus::None,
                'rollback_of_deployment_id' => $rollbackOf?->id,
                'plan_hash' => $planHash,
            ]));
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505'
                && str_contains($exception->getMessage(), 'deployments_one_mutation_uidx')) {
                throw new DeploymentLockConflict;
            }

            throw $exception;
        }
    }
}
