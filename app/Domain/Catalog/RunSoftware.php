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
use App\Models\PanelNotification;
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
            $report = null;
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
                        $verb = $job->type === 'software.upgrade' ? 'به‌روزرسانی' : 'نصب';
                        app(JobLogger::class)->append($job, 'info', 'درخواست '.$verb.' '.count($input['packages']).' بسته: '.implode(', ', $input['packages']).'. موفقیت پس از بررسی هر بسته اعلام می‌شود.', 'gate');
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
                    } elseif (($output['status'] ?? null) === 'running' && $job->started_at->gt(now()->subMinutes(35))) {
                        $report = app(SoftwareReport::class)->parse($output, $job, $input['packages']);
                        DB::transaction(function () use ($job, $report): void {
                            $values = ['status' => 'running', 'started_at' => $job->started_at];
                            if ($report !== null) {
                                $values['checkpoint'] = json_encode(app(SoftwareReport::class)->checkpoint($job, $report));
                            }
                            $job->steps()->where('key', 'gate')->update($values);
                        });
                        if (! $job->outboxMessages()->whereNull('published_at')->where('available_at', '>', now())->exists()) {
                            $job->outboxMessages()->create(['event_type' => 'job.queued', 'payload' => ['operation_job_id' => (string) $job->id], 'available_at' => now()->addSeconds(5), 'attempt' => 0]);
                        }

                        return;
                    } elseif (! app(OperationAccess::class)->mayExecute($job->fresh()) || $job->fresh()->cancel_requested
                        || ($job->type === 'setup.initialize' && ! app(PanelListener::class)->owner($job->actor->fresh(), $job->workspace->fresh()))) {
                        $error = 'AUTHORIZATION_REVOKED';
                    } elseif ($this->verified($output, $job, $input['packages'])) {
                        $report = app(SoftwareReport::class)->parse($output, $job, $input['packages']);
                        $evidence = array_intersect_key($output, array_flip(['schema_version', 'status', 'execution_id', 'operation', 'verified_at', 'verified', 'control_plane_verified', 'os', 'packages']));
                        $evidence['packages'] = array_map(fn ($package) => array_intersect_key($package, array_flip(['package', 'version', 'verified', 'changed'])), $evidence['packages']);
                    } else {
                        $report = app(SoftwareReport::class)->parse($output, $job, $input['packages']);
                        $error = preg_match('/^[A-Z_]{1,64}$/D', (string) ($output['error_code'] ?? '')) === 1 ? $output['error_code'] : 'SOFTWARE_VERIFICATION_FAILED';
                    }
                } catch (Throwable) {
                    $error = 'SOFTWARE_HELPER_UNAVAILABLE';
                }
            }
            DB::transaction(function () use ($job, $host, $evidence, $report, $error, $capability): void {
                $success = $evidence !== null;
                $job->forceFill(['status' => $success ? JobStatus::Succeeded : JobStatus::Failed, 'error_code' => $success ? null : $error, 'finished_at' => now()])->save();
                // A missing/untrusted report cannot turn an unobserved package into a success.
                $report ??= ['events' => [], 'packages' => array_map(fn ($package) => ['package' => $package, 'status' => 'skipped', 'phase' => 'pending', 'version' => null, 'verified' => false, 'changed' => false, 'error_code' => $error], $job->plan['input']['packages'] ?? [])];
                $checkpoint = app(SoftwareReport::class)->checkpoint($job, $report, $evidence ?? []);
                $job->steps()->where('key', 'gate')->update(['status' => $success ? 'succeeded' : 'failed', 'attempt' => $job->attempt, 'started_at' => $job->started_at, 'finished_at' => now(), 'checkpoint' => json_encode($checkpoint)]);
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
                $counts = ['total' => count($report['packages']), 'succeeded' => 0, 'failed' => 0, 'skipped' => 0];
                foreach ($report['packages'] as $package) {
                    $status = in_array($package['status'], ['succeeded', 'failed', 'skipped'], true) ? $package['status'] : 'skipped';
                    $counts[$status]++;
                    $description = match ($status) {
                        'succeeded' => $this->successLine($job->type, (bool) ($package['changed'] ?? false), (string) ($package['version'] ?? '')),
                        'failed' => 'ناموفق — '.$capability->errorMessage($package['error_code'] ?? $error ?? 'SOFTWARE_INSTALL_FAILED'),
                        default => 'اجرا نشد — '.$capability->errorMessage($package['error_code'] ?? $error ?? 'SOFTWARE_INSTALL_FAILED'),
                    };
                    app(JobLogger::class)->append($job, $status === 'succeeded' ? 'info' : 'error', ($capability->catalog()[$package['package']]['name'] ?? $package['package']).': '.$description, 'gate');
                }
                $summary = 'پایان بررسی '.$counts['total'].' بسته؛ موفق: '.$counts['succeeded'].'، ناموفق: '.$counts['failed'].'، اجرا‌نشده: '.$counts['skipped'].'.';
                app(JobLogger::class)->append($job, $success ? 'info' : 'error', $summary.($error === null ? '' : ' '.$capability->errorMessage($error)), 'gate');
                $audit = $job->type === 'software.upgrade' ? 'software.upgrade' : 'software.install';
                app(AuditRecorder::class)->record($job->workspace, $job->actor, $success ? $audit.'.verified' : $audit.'.failed', 'operation_job', $job->uuid, $success ? AuditResult::Success : AuditResult::Failure, ['packages' => $job->plan['input']['packages'] ?? [], 'verified' => $success, 'error_code' => $job->error_code, 'counts' => $counts], operationJob: $job);
                if ($job->actor !== null && $job->actor->memberships()->where('workspace_id', $job->workspace_id)->exists()) {
                    PanelNotification::query()->firstOrCreate(['event_key' => 'operation:'.$job->uuid.':terminal'], [
                        'workspace_id' => $job->workspace_id, 'recipient_id' => $job->actor_id, 'operation_job_id' => $job->id,
                        'type' => 'software.'.($success ? 'succeeded' : 'failed'), 'level' => $success ? 'success' : 'danger',
                        'title' => $success
                            ? ($job->type === 'software.upgrade' ? 'به‌روزرسانی و بررسی نرم‌افزار پایان یافت' : 'نصب و بررسی نرم‌افزار پایان یافت')
                            : ($job->type === 'software.upgrade' ? 'به‌روزرسانی نرم‌افزار کامل نشد' : 'نصب نرم‌افزار کامل نشد'),
                        'body' => $summary,
                        'action_path' => rtrim((string) config('rahandaz.base_path'), '/').'/jobs/'.$job->uuid,
                    ]);
                }
            });
        } finally {
            DB::select('select pg_advisory_unlock(hashtextextended(?, 0))', ['software.worker']);
        }
    }

    private function successLine(string $type, bool $changed, string $version): string
    {
        if ($type === 'software.upgrade') {
            return ($changed ? 'به‌روزرسانی و بررسی شد؛ نسخه ' : 'آخرین نسخهٔ در دسترس مخزن همین نسخه بود؛ نسخه ').$version;
        }

        return ($changed ? 'نصب و بررسی شد؛ نسخه ' : 'از قبل نصب بود و بررسی شد؛ نسخه ').$version;
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
        $report = app(SoftwareReport::class)->parse($output, $job, $packages);
        if ($report === null || collect($report['packages'])->contains(fn ($row) => $row['status'] !== 'succeeded')) {
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
