<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Enums\StepStatus;
use App\Domain\Jobs\JobLifecycleRecorder;
use App\Domain\Jobs\JobLogger;
use App\Models\LocalHost;
use App\Models\OperationJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Throwable;

final class RunPanelPort
{
    public function __construct(private readonly PanelListener $listener) {}

    public function run(int|string $id): void
    {
        $lock = DB::selectOne('select pg_try_advisory_lock(hashtextextended(?, 0)) as acquired', ['panel.port.worker']);
        if (! filter_var($lock->acquired, FILTER_VALIDATE_BOOL)) {
            throw new \RuntimeException('Panel port worker is busy.');
        }
        try {
            $job = DB::transaction(function () use ($id): ?OperationJob {
                $job = OperationJob::query()->lockForUpdate()->find($id);
                if ($job === null || $job->status->isTerminal() || ! in_array($job->status, [JobStatus::Queued, JobStatus::WaitingRetry, JobStatus::Running], true)) {
                    return null;
                }
                $job->forceFill(['status' => JobStatus::Running, 'attempt' => $job->attempt + 1, 'started_at' => $job->started_at ?? now()])->save();
                app(JobLifecycleRecorder::class)->record($job, 'started');

                return $job;
            });
            if ($job === null) {
                return;
            }
            $code = 'PANEL_PORT_FAILED';
            $evidence = null;
            $input = $job->plan['input'] ?? [];
            $host = $job->resource_type === 'local_host' ? LocalHost::query()->forWorkspace($job->workspace_id)->find($job->resource_id) : null;
            if ($job->actor === null || ! $this->listener->owner($job->actor, $job->workspace) || $host?->slug !== 'local-host') {
                $code = 'AUTHORIZATION_REVOKED';
            } elseif ($job->cancel_requested) {
                $code = 'CANCELLED_BEFORE_EXECUTION';
            } elseif ($this->listener->state() === null) {
                $code = 'PANEL_LISTENER_DISABLED';
            } elseif (($input['contract_version'] ?? null) !== '1' || ! is_int($input['port'] ?? null) || ! $this->listener->validPort($input['port']) || ! is_int($input['expected_port'] ?? null)) {
                $code = 'INVALID_CONTRACT';
            } else {
                try {
                    $result = Process::timeout(48)->input(json_encode([
                        'schema_version' => '1', 'operation' => 'panel.port.update', 'execution_id' => $job->uuid,
                        'expected_port' => $input['expected_port'], 'port' => $input['port'],
                    ], JSON_THROW_ON_ERROR))->run(['/usr/bin/python3', '-I', '/var/lib/rahandaz/current/infrastructure/operations/panel/client.py']);
                    $output = json_decode($result->output(), true);
                    if ($result->successful() && is_array($output) && ($output['schema_version'] ?? null) === '1' && ($output['status'] ?? null) === 'succeeded' && ($output['verified'] ?? null) === true && ($output['port'] ?? null) === $input['port']) {
                        $evidence = $output;
                    } elseif (is_array($output) && preg_match('/^[A-Z_]{1,64}$/D', (string) ($output['error_code'] ?? '')) === 1) {
                        $code = $output['error_code'];
                    }
                } catch (Throwable) {
                    $code = 'PANEL_PORT_HELPER_FAILED';
                }
            }
            DB::transaction(function () use ($job, $evidence, $code): void {
                $job->forceFill(['status' => $evidence !== null ? JobStatus::Succeeded : JobStatus::Failed, 'error_code' => $evidence !== null ? null : $code, 'finished_at' => now()])->save();
                $job->steps()->where('key', 'gate')->update(['status' => $evidence !== null ? StepStatus::Succeeded->value : StepStatus::Failed->value, 'attempt' => $job->attempt, 'started_at' => $job->started_at, 'finished_at' => now(), 'checkpoint' => $evidence !== null ? json_encode($evidence) : null]);
                app(JobLogger::class)->append($job, $evidence !== null ? 'info' : 'error', $evidence !== null ? 'پورت جدید پس از بررسی واقعی سلامت فعال شد.' : 'تغییر پورت موفق نشد؛ کد خطا: '.$code, 'gate');
                app(AuditRecorder::class)->record($job->workspace, $job->actor, $evidence !== null ? 'panel.port.applied' : 'panel.port.failed', 'operation_job', $job->uuid, $evidence !== null ? AuditResult::Success : AuditResult::Failure, ['port' => $job->plan['input']['port'] ?? null, 'host_executed' => $evidence !== null, 'error_code' => $job->error_code], operationJob: $job);
            });
        } finally {
            DB::select('select pg_advisory_unlock(hashtextextended(?, 0))', ['panel.port.worker']);
        }
    }
}
