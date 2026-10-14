<?php

declare(strict_types=1);

namespace App\Domain\Projects;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Enums\StepStatus;
use App\Domain\Jobs\RecordOperation;
use App\Domain\Projects\Data\QueuedDeployment;
use App\Domain\Projects\Enums\DeploymentStatus;
use App\Domain\Projects\Enums\DeploymentTrigger;
use App\Domain\Projects\Enums\ProjectSourceType;
use App\Domain\Projects\Enums\ReleaseState;
use App\Domain\Projects\Exceptions\DeploymentLockConflict;
use App\Domain\Projects\Exceptions\DeploymentRejected;
use App\Models\Deployment;
use App\Models\Environment;
use App\Models\OperationJob;
use App\Models\Release;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeploymentWorkflow
{
    public function __construct(
        private readonly DeploymentRecorder $recorder,
        private readonly RecordOperation $operations,
        private readonly AuditRecorder $audit,
    ) {}

    public function queueManual(
        Environment $environment,
        User $actor,
        string $commitSha,
        ?string $requestId,
        ?string $sourceIp,
        ?string $idempotencyKey = null,
    ): QueuedDeployment {
        $environment->loadMissing('project.workspace');

        return $this->queue(
            $environment,
            $actor,
            $commitSha,
            DeploymentTrigger::Manual,
            null,
            'deploy.prepare',
            'deployment.queue',
            $requestId,
            $sourceIp,
            $idempotencyKey,
        );
    }

    public function retry(
        Deployment $source,
        User $actor,
        ?string $requestId,
        ?string $sourceIp,
    ): QueuedDeployment {
        if (! in_array($source->status, [DeploymentStatus::Failed, DeploymentStatus::Cancelled], true)) {
            throw new DeploymentRejected('تلاش دوباره فقط برای استقرار ناموفق یا لغوشده ثبت می‌شود.');
        }

        if ($source->source_type !== ProjectSourceType::Git || ! is_string($source->commit_sha)) {
            throw new DeploymentRejected('تلاش دوباره در این نسخه فقط برای استقرار Git با commit ثبت‌شده است.');
        }

        $source->loadMissing('environment.project.workspace');
        $environment = $source->environment;

        if (! $environment instanceof Environment) {
            throw new DeploymentRejected('محیط این استقرار پیدا نشد.');
        }

        return $this->queue(
            $environment,
            $actor,
            $source->commit_sha,
            DeploymentTrigger::Retry,
            null,
            'deploy.prepare',
            'deployment.retry',
            $requestId,
            $sourceIp,
        );
    }

    public function rollback(
        Deployment $source,
        User $actor,
        ?string $requestId,
        ?string $sourceIp,
    ): QueuedDeployment {
        $reason = $this->rollbackBlockReason($source);

        if ($reason !== null) {
            throw new DeploymentRejected($reason);
        }

        if (! is_string($source->commit_sha)) {
            throw new DeploymentRejected('این استقرار commit ثابتی برای بازگشت ندارد.');
        }

        $source->loadMissing('environment.project.workspace');
        $environment = $source->environment;

        if (! $environment instanceof Environment) {
            throw new DeploymentRejected('محیط این استقرار پیدا نشد.');
        }

        return $this->queue(
            $environment,
            $actor,
            $source->commit_sha,
            DeploymentTrigger::Rollback,
            $source,
            'deploy.rollback',
            'deployment.rollback',
            $requestId,
            $sourceIp,
        );
    }

    public function rollbackBlockReason(Deployment $source): ?string
    {
        // Schema compatibility and host release verification are not implemented yet.
        return 'بازگشت تا تکمیل بررسی سازگاری پایگاه، release واقعی و آزمون VM در دسترس نیست.';
    }

    private function releaseBlockReason(Deployment $source): ?string
    {
        if ($source->status !== DeploymentStatus::Succeeded) {
            return 'بازگشت فقط وقتی ثبت می‌شود که ساخت و سلامت استقرار مبدأ واقعاً موفق شده باشد.';
        }

        $source->loadMissing('targetRelease');
        $release = $source->targetRelease;

        if (! $release instanceof Release || ! is_string($release->directory_path) || $release->directory_path === '') {
            return 'release سازگار با مسیر واقعی برای بازگشت ثبت نشده است.';
        }

        if (! in_array($release->state, [ReleaseState::Active, ReleaseState::Previous, ReleaseState::Ready, ReleaseState::Retained], true)) {
            return 'وضعیت release مبدأ برای بازگشت سازگار نیست.';
        }

        $locked = Deployment::query()
            ->where('environment_id', $source->environment_id)
            ->mutating()
            ->exists();

        if ($locked) {
            return 'یک استقرار تغییردهنده برای این محیط در جریان است.';
        }

        return null;
    }

    public function cancel(Deployment $deployment, User $actor, ?string $requestId, ?string $sourceIp): Deployment
    {
        $deployment->loadMissing('environment.project.workspace');

        return DB::transaction(function () use ($deployment, $actor, $requestId, $sourceIp): Deployment {
            // Same lock order as worker: operation first, then deployment.
            $job = $deployment->operation_job_id === null
                ? null
                : OperationJob::query()->lockForUpdate()->find($deployment->operation_job_id);
            $locked = Deployment::query()->lockForUpdate()->findOrFail($deployment->id);
            $locked->loadMissing('workspace');

            if ($locked->status !== DeploymentStatus::Queued) {
                throw new DeploymentRejected('فقط استقرار هنوز شروع‌نشده را می‌توان لغو کرد.');
            }

            if ($job instanceof OperationJob && ! $job->status->isTerminal()) {
                if ($job->status !== JobStatus::Queued && $job->status !== JobStatus::WaitingRetry) {
                    $job->forceFill(['cancel_requested' => true])->save();
                    $locked->loadMissing('workspace');
                    $this->audit->record(
                        $locked->workspace,
                        $actor,
                        'deployment.cancel',
                        'deployment',
                        $locked->uuid,
                        AuditResult::Success,
                        [
                            'host_executed' => false,
                            'cancel_requested' => true,
                            'operation_job_uuid' => $job->uuid,
                        ],
                        $requestId,
                        $sourceIp,
                        $job,
                    );

                    return $locked->fresh() ?? $locked;
                }

                $finishedAt = now();
                $job->forceFill([
                    'status' => JobStatus::Cancelled,
                    'cancel_requested' => true,
                    'finished_at' => $finishedAt,
                    'error_code' => 'CANCELLED',
                ])->save();
                $job->steps()->where('status', StepStatus::Queued->value)->update([
                    'status' => StepStatus::Skipped->value,
                    'finished_at' => $finishedAt,
                ]);
            }

            $locked->forceFill([
                'status' => DeploymentStatus::Cancelled,
                'finished_at' => now(),
                'failure_code' => 'CANCELLED',
            ])->save();

            $this->audit->record(
                $locked->workspace,
                $actor,
                'deployment.cancel',
                'deployment',
                $locked->uuid,
                AuditResult::Success,
                [
                    'host_executed' => false,
                    'operation_job_uuid' => $job?->uuid,
                ],
                $requestId,
                $sourceIp,
                $job,
            );

            return $locked->fresh() ?? $locked;
        });
    }

    private function queue(
        Environment $environment,
        User $actor,
        string $commitSha,
        DeploymentTrigger $trigger,
        ?Deployment $rollbackOf,
        string $operationType,
        string $auditAction,
        ?string $requestId,
        ?string $sourceIp,
        ?string $idempotencyKey = null,
    ): QueuedDeployment {
        try {
            return DB::transaction(function () use ($environment, $actor, $commitSha, $trigger, $rollbackOf, $operationType, $auditAction, $requestId, $sourceIp, $idempotencyKey): QueuedDeployment {
                DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['deployment-environment:'.$environment->id]);
                if ($idempotencyKey !== null) {
                    $existing = OperationJob::query()->where('workspace_id', $environment->workspace_id)
                        ->where('actor_id', $actor->id)->where('type', $operationType)->where('idempotency_key', $idempotencyKey)->first();
                    if ($existing !== null) {
                        if (($existing->plan['input']['commit_sha'] ?? null) !== $commitSha || (string) $existing->resource_id !== (string) $environment->id) {
                            throw new DeploymentRejected('کلید درخواست قبلی با ورودی متفاوت استفاده شده است.');
                        }
                        $old = Deployment::query()->where('operation_job_id', $existing->id)->firstOrFail();

                        return new QueuedDeployment($old, $existing);
                    }
                }
                $deployment = $this->recorder->queue($environment, $actor, $commitSha, $trigger, null, $rollbackOf);
                $operation = $this->operations->record(new RecordOperationData(
                    workspaceId: $environment->workspace_id,
                    actorId: $actor->id,
                    type: $operationType,
                    idempotencyKey: $idempotencyKey ?? 'deployment:'.$deployment->uuid,
                    plan: [
                        'source_type' => 'git',
                        'commit_sha' => $commitSha,
                        'project_uuid' => $environment->project?->uuid,
                        'environment_uuid' => $environment->uuid,
                        'trigger' => $trigger->value,
                        'rollback_of_uuid' => $rollbackOf?->uuid,
                        'host_executed' => false,
                    ],
                    mutatesHost: true,
                    resourceType: 'environment',
                    resourceId: $environment->id,
                ));
                $deployment->operation_job_id = $operation->id;
                $deployment->save();

                $this->audit->record(
                    $environment->project?->workspace,
                    $actor,
                    $auditAction,
                    'deployment',
                    $deployment->uuid,
                    AuditResult::Success,
                    [
                        'host_executed' => false,
                        'commit_sha' => $commitSha,
                        'environment_uuid' => $environment->uuid,
                        'operation_job_uuid' => $operation->uuid,
                        'trigger' => $trigger->value,
                    ],
                    $requestId,
                    $sourceIp,
                    $operation,
                );

                return new QueuedDeployment($deployment->fresh() ?? $deployment, $operation);
            });
        } catch (DeploymentLockConflict) {
            throw new DeploymentRejected('برای این محیط یک استقرار تغییردهنده در انتظار یا در حال اجرا است.');
        }
    }
}
