<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Models\Membership;
use App\Models\OperationJob;
use App\Models\PanelNotification;

final class JobLifecycleRecorder
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function record(OperationJob $job, string $phase): void
    {
        $job->loadMissing('workspace', 'actor');
        $started = $phase === 'started';
        $this->audit->record($job->workspace, $job->actor, $started ? 'operation.started' : 'operation.failed', 'operation_job', $job->uuid,
            $started ? AuditResult::Success : AuditResult::Failure, ['error_code' => $job->error_code, 'host_executed' => false], operationJob: $job);
        if ($job->actor_id === null || ! Membership::query()->where('workspace_id', $job->workspace_id)->where('user_id', $job->actor_id)->exists()) {
            return;
        }
        PanelNotification::query()->firstOrCreate(['event_key' => 'operation:'.$job->uuid.':'.$phase], [
            'workspace_id' => $job->workspace_id, 'recipient_id' => $job->actor_id, 'operation_job_id' => $job->id,
            'type' => 'operation.'.$phase, 'level' => $started ? 'info' : 'danger',
            'title' => $started ? 'بررسی عملیات آغاز شد' : 'عملیات اجرا نشد',
            'body' => $started ? 'Worker درخواست را دریافت کرد؛ نتیجه میزبان هنوز تأیید نشده است.' : 'تغییر میزبان انجام نشد. کد خطا: '.$job->error_code,
            'action_path' => rtrim((string) config('rahandaz.base_path'), '/').'/jobs/'.$job->uuid,
        ]);
    }
}
