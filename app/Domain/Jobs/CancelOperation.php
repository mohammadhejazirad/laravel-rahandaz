<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Enums\StepStatus;
use App\Domain\Projects\Enums\DeploymentStatus;
use App\Models\Deployment;
use App\Models\OperationJob;
use App\Models\PanelNotification;
use App\Models\RestoreOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelOperation
{
    public function __construct(
        private readonly CancelDecision $decision,
        private readonly AuditRecorder $audit,
        private readonly JobLogger $logger,
    ) {}

    public function request(OperationJob $job, User $actor, ?string $sourceIp): string
    {
        return DB::transaction(function () use ($job, $actor, $sourceIp): string {
            $locked = OperationJob::query()->lockForUpdate()->findOrFail($job->id);
            $mode = $this->decision->mode($locked->status);
            if ($mode === 'closed') {
                throw ValidationException::withMessages(['job' => 'این عملیات تمام شده است و لغو نمی‌شود.']);
            }
            if ($mode === 'flag' && $locked->cancel_requested) {
                throw ValidationException::withMessages(['job' => 'درخواست لغو قبلاً ثبت شده است.']);
            }

            $locked->forceFill(['cancel_requested' => true])->save();
            if ($mode === 'finish') {
                $this->finishLocked($locked);
            }

            $locked->loadMissing('workspace');
            $this->audit->record(
                $locked->workspace,
                $actor,
                'job.cancel_requested',
                'operation_job',
                $locked->uuid,
                AuditResult::Success,
                ['host_executed' => false, 'mode' => $mode],
                null,
                $sourceIp,
                $locked,
            );
            if ($locked->actor_id !== null) {
                PanelNotification::query()->firstOrCreate(['event_key' => 'operation:'.$locked->uuid.':cancel'], [
                    'workspace_id' => $locked->workspace_id,
                    'recipient_id' => $locked->actor_id,
                    'operation_job_id' => $locked->id,
                    'type' => 'operation.cancel',
                    'level' => 'warning',
                    'title' => 'درخواست لغو ثبت شد',
                    'body' => $mode === 'finish'
                        ? 'عملیات پیش از شروع لغو شد. فرمانی روی میزبان از این درخواست اجرا نشد.'
                        : 'درخواست لغو ذخیره شد. توقف فرایندی که شاید شروع شده باشد هنوز اثبات نشده است.',
                    'action_path' => rtrim((string) config('rahandaz.base_path'), '/').'/jobs/'.$locked->uuid,
                ]);
            }

            return $mode;
        });
    }

    public function finishLocked(OperationJob $locked): void
    {
        if ($locked->status->isTerminal()) {
            return;
        }

        $finishedAt = now();
        $locked->forceFill([
            'status' => JobStatus::Cancelled,
            'cancel_requested' => true,
            'finished_at' => $finishedAt,
            'error_code' => 'CANCELLED',
        ])->save();
        $locked->steps()->where('status', StepStatus::Queued->value)->update([
            'status' => StepStatus::Skipped->value,
            'finished_at' => $finishedAt,
        ]);
        Deployment::query()->where('operation_job_id', $locked->id)->where('status', DeploymentStatus::Queued->value)->update([
            'status' => DeploymentStatus::Cancelled->value,
            'finished_at' => $finishedAt,
            'failure_code' => 'CANCELLED',
        ]);
        RestoreOperation::query()->where('operation_job_id', $locked->id)->where('status', 'queued')->update([
            'status' => 'cancelled',
            'failure_code' => 'CANCELLED',
            'finished_at' => $finishedAt,
            'updated_at' => $finishedAt,
        ]);
        $this->logger->append($locked, 'warning', 'درخواست لغو پیش از اجرای میزبان ثبت شد.', 'gate');
    }
}
