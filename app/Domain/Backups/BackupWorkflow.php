<?php

declare(strict_types=1);

namespace App\Domain\Backups;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Backups\Enums\BackupState;
use App\Domain\Backups\Enums\RestoreApproval;
use App\Domain\Backups\Exceptions\BackupRejected;
use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\RecordOperation;
use App\Models\Backup;
use App\Models\LocalHost;
use App\Models\OperationJob;
use App\Models\ProjectDatabase;
use App\Models\RestoreOperation;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

final class BackupWorkflow
{
    public function __construct(
        private readonly RecordOperation $operations,
        private readonly AuditRecorder $audit,
    ) {}

    public function queueDatabase(ProjectDatabase $database, User $actor, string $idempotencyKey, ?string $requestId, ?string $sourceIp): OperationJob
    {
        $database->loadMissing('databaseInstance.workspace');
        $workspace = $database->databaseInstance?->workspace;

        if (! $workspace instanceof Workspace) {
            throw new BackupRejected('فضای کاری دیتابیس پیدا نشد.');
        }

        if ($database->status->value !== 'ready') {
            throw new BackupRejected('پشتیبان فقط برای دیتابیسی ثبت می‌شود که وضعیت واقعی آن آماده باشد. درخواست هنوز ساخته‌نشده، فایل پشتیبان ندارد.');
        }

        return $this->queue(
            $workspace,
            $actor,
            'backup.database',
            'project_database',
            $database->id,
            [
                'database_uuid' => $database->uuid,
                'host_executed' => false,
                'artifact_created' => false,
            ],
            'backup.queue',
            $database->uuid,
            $idempotencyKey,
            $requestId,
            $sourceIp,
        );
    }

    public function queueControlPlane(LocalHost $host, User $actor, string $idempotencyKey, ?string $requestId, ?string $sourceIp): OperationJob
    {
        $host->loadMissing('workspace');

        if (! $host->workspace instanceof Workspace) {
            throw new BackupRejected('فضای کاری میزبان پیدا نشد.');
        }

        return $this->queue(
            $host->workspace,
            $actor,
            'backup.control_plane',
            'local_host',
            $host->id,
            [
                'host_uuid' => $host->uuid,
                'host_executed' => false,
                'artifact_created' => false,
            ],
            'backup.queue',
            $host->uuid,
            $idempotencyKey,
            $requestId,
            $sourceIp,
        );
    }

    public function saveSchedule(
        Workspace $workspace,
        User $actor,
        string $operationKey,
        string $cron,
        string $timezone,
        ?string $resourceType,
        ?int $resourceId,
        ?string $requestId,
        ?string $sourceIp,
    ): Schedule {
        if (! in_array($operationKey, ['backup.database', 'backup.control_plane'], true)) {
            throw new BackupRejected('کلید زمان‌بندی مجاز نیست.');
        }

        if (preg_match('/^([0-9*,\/-]{1,20} ){4}[0-9*,\/-]{1,20}$/', $cron) !== 1) {
            throw new BackupRejected('عبارت زمان‌بندی معتبر نیست.');
        }

        if (! in_array($timezone, ['Asia/Tehran', 'UTC'], true)) {
            throw new BackupRejected('منطقه زمانی مجاز نیست.');
        }

        if ($operationKey === 'backup.database' && ($resourceType !== 'project_database' || $resourceId === null)) {
            throw new BackupRejected('زمان‌بندی دیتابیس باید به یک دیتابیس همین فضای کاری وصل باشد.');
        }

        if ($operationKey === 'backup.control_plane' && ($resourceType !== 'local_host' || $resourceId === null)) {
            throw new BackupRejected('زمان‌بندی صفحه کنترل باید به میزبان همین فضای کاری وصل باشد.');
        }

        $schedule = Schedule::query()->create([
            'workspace_id' => $workspace->id,
            'operation_key' => $operationKey,
            'timezone' => $timezone,
            'cron_expression' => $cron,
            'enabled' => false,
            'overlap_policy' => 'skip',
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]);

        $this->audit->record(
            $workspace,
            $actor,
            'backup.schedule',
            'schedule',
            $schedule->uuid,
            AuditResult::Success,
            [
                'operation_key' => $operationKey,
                'enabled' => false,
                'runner_connected' => false,
            ],
            $requestId,
            $sourceIp,
        );

        return $schedule;
    }

    public function requestRestore(Backup $backup, User $actor, string $confirmation, ?string $requestId, ?string $sourceIp): RestoreOperation
    {
        if (! hash_equals('RESTORE', $confirmation)) {
            throw new BackupRejected('برای درخواست بازیابی باید عبارت RESTORE را وارد کنید.');
        }

        if ($backup->state !== BackupState::Succeeded || $backup->checksum_sha256 === null || $backup->verified_at === null) {
            throw new BackupRejected('بازیابی فقط برای پشتیبان موفق، دارای checksum و راستی‌آزمایی‌شده قابل درخواست است. این بازیابی تأییدشده نیست.');
        }

        $backup->loadMissing('workspace');

        return DB::transaction(function () use ($backup, $actor, $requestId, $sourceIp): RestoreOperation {
            $restore = RestoreOperation::query()->create([
                'backup_id' => $backup->id,
                'workspace_id' => $backup->workspace_id,
                'actor_id' => $actor->id,
                'status' => 'queued',
                'approval_state' => RestoreApproval::Pending,
                'operation_job_id' => null,
            ]);
            $this->audit->record(
                $backup->workspace,
                $actor,
                'restore.request',
                'restore_operation',
                $restore->uuid,
                AuditResult::Success,
                [
                    'backup_uuid' => $backup->uuid,
                    'approval_state' => RestoreApproval::Pending->value,
                    'host_executed' => false,
                ],
                $requestId,
                $sourceIp,
            );

            return $restore;
        });
    }

    public function approveRestore(RestoreOperation $restore, User $actor, ?string $requestId, ?string $sourceIp): RestoreOperation
    {
        // Metadata alone cannot prove artifact integrity, target compatibility,
        // recovery capability, or a plan-bound destructive approval.
        throw new BackupRejected('تأیید بازیابی تا بررسی فایل واقعی، مجوز مقصد، برنامه بازیابی و آزمون ایزوله در دسترس نیست.');
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function queue(
        Workspace $workspace,
        User $actor,
        string $type,
        string $resourceType,
        int|string $resourceId,
        array $plan,
        string $auditAction,
        string $resourceUuid,
        string $idempotencyKey,
        ?string $requestId,
        ?string $sourceIp,
    ): OperationJob {
        if (preg_match('/^[A-Za-z0-9_:-]{8,80}$/', $idempotencyKey) !== 1) {
            throw new BackupRejected('کلید تکرار درخواست معتبر نیست.');
        }

        return DB::transaction(function () use ($workspace, $actor, $type, $resourceType, $resourceId, $plan, $auditAction, $resourceUuid, $idempotencyKey, $requestId, $sourceIp): OperationJob {
            $operation = $this->operations->record(new RecordOperationData(
                workspaceId: $workspace->id,
                actorId: $actor->id,
                type: $type,
                idempotencyKey: $idempotencyKey,
                plan: $plan,
                mutatesHost: true,
                resourceType: $resourceType,
                resourceId: $resourceId,
            ));
            $this->audit->record(
                $workspace,
                $actor,
                $auditAction,
                $resourceType,
                $resourceUuid,
                AuditResult::Success,
                [
                    'operation_job_uuid' => $operation->uuid,
                    'host_executed' => false,
                    'artifact_created' => false,
                ],
                $requestId,
                $sourceIp,
                $operation,
            );

            return $operation;
        });
    }
}
