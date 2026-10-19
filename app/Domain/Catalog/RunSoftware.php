<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Access\OperationAccess;
use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\JobLifecycleRecorder;
use App\Domain\Jobs\JobLogger;
use App\Domain\Settings\PanelListener;
use App\Infrastructure\Host\ReadOnlyHostProbe;
use App\Models\LocalHost;
use App\Models\OperationJob;
use App\Models\SetupSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Throwable;

final class RunSoftware
{
    public function run(int|string $id): void
    {
        $lock = DB::selectOne('select pg_try_advisory_lock(hashtextextended(?, 0)) as acquired', ['software.worker']);
        if (! filter_var($lock->acquired, FILTER_VALIDATE_BOOL)) {
            throw new \RuntimeException('Software worker is busy.');
        }
        try {
            $job = OperationJob::query()->find($id);
            if ($job === null || $job->status->isTerminal()) {
                return;
            }
            $host = $job->resource_type === 'local_host' ? LocalHost::query()->forWorkspace($job->workspace_id)->find($job->resource_id) : null;
            $input = $job->plan['input'] ?? [];
            $capability = app(SoftwareCapability::class);
            $error = null;
            $evidence = null;
            if ($host?->slug !== 'local-host' || ! app(OperationAccess::class)->mayExecute($job)
                || ($job->type === 'setup.initialize' && ($job->actor === null || ! app(PanelListener::class)->owner($job->actor, $job->workspace)))) {
                $error = 'AUTHORIZATION_REVOKED';
            } elseif ($job->cancel_requested) {
                $error = 'CANCELLED_BEFORE_EXECUTION';
            } elseif (! $capability->available()) {
                $error = 'SOFTWARE_HELPER_UNAVAILABLE';
            } elseif (($input['contract_version'] ?? null) !== '1' || ! is_array($input['packages'] ?? null)) {
                $error = 'INVALID_CONTRACT';
            } else {
                try {
                    $capability->validate($input['packages']);
                    if ($job->status !== JobStatus::Running) {
                        $job->forceFill(['status' => JobStatus::Running, 'attempt' => $job->attempt + 1, 'started_at' => $job->started_at ?? now()])->save();
                        app(JobLifecycleRecorder::class)->record($job, 'started');
                    }
                    $job->forceFill(['heartbeat_at' => now()])->save();
                    // Start is idempotent: a redelivered message reconciles the root journal.
                    $result = Process::timeout(12)->input(json_encode([
                        'schema_version' => '1', 'action' => 'start', 'operation' => $job->type,
                        'execution_id' => $job->uuid, 'packages' => $input['packages'],
                    ], JSON_THROW_ON_ERROR))->run(['/usr/bin/python3', '-I', '/var/lib/rahandaz/current/infrastructure/operations/software/client.py']);
                    $output = json_decode($result->output(), true);
                    if (! $result->successful() || ! is_array($output) || ($output['schema_version'] ?? null) !== '1') {
                        $error = 'SOFTWARE_HELPER_UNAVAILABLE';
                    } elseif (($output['status'] ?? null) === 'running' && $job->started_at->gt(now()->subMinutes(20))) {
                        $job->steps()->where('key', 'gate')->update(['status' => 'running', 'started_at' => $job->started_at]);
                        if (! $job->outboxMessages()->whereNull('published_at')->where('available_at', '>', now())->exists()) {
                            $job->outboxMessages()->create(['event_type' => 'job.queued', 'payload' => ['operation_job_id' => (string) $job->id], 'available_at' => now()->addSeconds(5), 'attempt' => 0]);
                        }

                        return;
                    } elseif (! app(OperationAccess::class)->mayExecute($job->fresh()) || $job->fresh()->cancel_requested
                        || ($job->type === 'setup.initialize' && ! app(PanelListener::class)->owner($job->actor->fresh(), $job->workspace->fresh()))) {
                        $error = 'AUTHORIZATION_REVOKED';
                    } elseif ($this->verified($output, $job, $input['packages'])) {
                        $evidence = array_intersect_key($output, array_flip(['schema_version', 'status', 'execution_id', 'operation', 'verified_at', 'verified', 'control_plane_verified', 'os', 'packages']));
                        $evidence['packages'] = array_map(fn ($package) => array_intersect_key($package, array_flip(['package', 'version', 'verified', 'changed'])), $evidence['packages']);
                    } else {
                        $error = preg_match('/^[A-Z_]{1,64}$/D', (string) ($output['error_code'] ?? '')) === 1 ? $output['error_code'] : 'SOFTWARE_VERIFICATION_FAILED';
                    }
                } catch (Throwable) {
                    $error = 'SOFTWARE_HELPER_UNAVAILABLE';
                }
            }
            DB::transaction(function () use ($job, $host, $evidence, $error, $capability): void {
                $success = $evidence !== null;
                $job->forceFill(['status' => $success ? JobStatus::Succeeded : JobStatus::Failed, 'error_code' => $success ? null : $error, 'finished_at' => now()])->save();
                $job->steps()->where('key', 'gate')->update(['status' => $success ? 'succeeded' : 'failed', 'attempt' => $job->attempt, 'started_at' => $job->started_at, 'finished_at' => now(), 'checkpoint' => $success ? json_encode($evidence) : null]);
                if ($job->type === 'setup.initialize') {
                    $session = SetupSession::query()->forWorkspace($job->workspace_id)->where('operation_job_id', $job->id)->lockForUpdate()->firstOrFail();
                    $session->forceFill(['state' => $success ? 'completed' : 'blocked', 'error_code' => $job->error_code, 'finished_at' => now()])->save();
                    $session->components()->update(['state' => $success ? 'succeeded' : 'failed', 'error_code' => $job->error_code]);
                    if ($success) {
                        $facts = app(ReadOnlyHostProbe::class)->collect();
                        $host->forceFill(['display_name' => $session->answers['display_name'], 'hostname' => $facts['hostname'], 'state' => 'compatible', 'last_seen_at' => now()])->save();
                        $host->facts()->create(['observed_at' => now(), 'hostname' => $facts['hostname'], 'os_name' => $facts['os']['name'] ?? null, 'os_version' => $evidence['os'], 'os_codename' => $facts['os']['version_codename'] ?? null,
                            'kernel' => $facts['kernel'], 'architecture' => $facts['architecture'], 'ram_bytes' => ($facts['memory']['total_kib'] ?? 0) * 1024, 'systemd_health' => 'running', 'compatibility' => 'untested', 'collector' => 'readonly_probe']);
                        $job->workspace->forceFill(['setup_completed_at' => now()])->save();
                    }
                }
                app(JobLogger::class)->append($job, $success ? 'info' : 'error', $success ? 'بررسی سلامت و نصب نرم‌افزارهای انتخاب‌شده تأیید شد.' : $capability->errorMessage($error ?? 'SOFTWARE_INSTALL_FAILED'), 'gate');
                app(AuditRecorder::class)->record($job->workspace, $job->actor, $success ? 'software.install.verified' : 'software.install.failed', 'operation_job', $job->uuid, $success ? AuditResult::Success : AuditResult::Failure, ['packages' => $job->plan['input']['packages'] ?? [], 'verified' => $success, 'error_code' => $job->error_code], operationJob: $job);
            });
        } finally {
            DB::select('select pg_advisory_unlock(hashtextextended(?, 0))', ['software.worker']);
        }
    }

    private function verified(array $output, OperationJob $job, array $packages): bool
    {
        if (($output['status'] ?? null) !== 'succeeded' || ($output['verified'] ?? null) !== true || ($output['control_plane_verified'] ?? null) !== true
            || ! is_int($output['verified_at'] ?? null) || $output['verified_at'] > now()->timestamp + 5 || $output['verified_at'] < now()->subMinutes(5)->timestamp
            || ($output['execution_id'] ?? null) !== $job->uuid || ($output['operation'] ?? null) !== $job->type
            || ! in_array($output['os'] ?? null, ['22.04', '24.04'], true) || ! is_array($output['packages'] ?? null) || ! array_is_list($output['packages'])
            || array_column($output['packages'], 'package') !== $packages) {
            return false;
        }
        foreach ($output['packages'] as $package) {
            if (($package['verified'] ?? null) !== true || ! is_bool($package['changed'] ?? null) || preg_match('/^[A-Za-z0-9.+:~_-]{1,120}$/D', (string) ($package['version'] ?? '')) !== 1) {
                return false;
            }
        }

        return true;
    }
}
