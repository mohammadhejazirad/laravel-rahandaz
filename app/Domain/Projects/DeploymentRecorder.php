<?php

declare(strict_types=1);

namespace App\Domain\Projects;

use App\Domain\Projects\Enums\DeploymentStatus;
use App\Domain\Projects\Enums\DeploymentTrigger;
use App\Domain\Projects\Enums\PhaseStatus;
use App\Domain\Projects\Enums\ProjectSourceType;
use App\Domain\Projects\Enums\RollbackStatus;
use App\Domain\Projects\Exceptions\DeploymentLockConflict;
use App\Models\Deployment;
use App\Models\Environment;
use App\Models\ProjectSourceArchive;
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
        if (preg_match('/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/D', $commitSha) !== 1) {
            throw new InvalidArgumentException('An immutable full commit SHA is required.');
        }
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
                'source_type' => ProjectSourceType::Git,
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

    public function queueArchive(
        Environment $environment,
        User $actor,
        ProjectSourceArchive $archive,
    ): Deployment {
        $environment->loadMissing('project');

        if ((string) $archive->project_id !== (string) $environment->project_id
            || (string) $archive->workspace_id !== (string) $environment->workspace_id) {
            throw new InvalidArgumentException('Archive and environment must belong to the same project.');
        }

        $planHash = hash('sha256', $environment->id.'|archive|'.$archive->sha256);

        try {
            return DB::transaction(fn (): Deployment => Deployment::query()->create([
                'environment_id' => $environment->id,
                'workspace_id' => $environment->workspace_id,
                'remote_repository_id' => null,
                'source_type' => ProjectSourceType::Archive,
                'source_archive_id' => $archive->id,
                'commit_sha' => null,
                'trigger_type' => DeploymentTrigger::Manual,
                'actor_id' => $actor->id,
                'status' => DeploymentStatus::Queued,
                'artifact_digest' => $archive->sha256,
                'build_status' => PhaseStatus::Pending,
                'health_status' => PhaseStatus::Pending,
                'rollback_status' => RollbackStatus::None,
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
