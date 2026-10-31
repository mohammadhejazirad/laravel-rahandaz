<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Access\OperationAccess;
use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Enums\StepStatus;
use App\Domain\Jobs\RecordOperation;
use App\Models\LocalHost;
use App\Models\OperationJob;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

final class ChromeInstallation
{
    public function request(Workspace $workspace, LocalHost $host, User $actor, ?string $requestId, ?string $ip): OperationJob
    {
        abort_unless((string) $host->workspace_id === (string) $workspace->id && $host->slug === 'local-host', 404);
        abort_unless(app(OperationAccess::class)->managesWorkspace($actor, $workspace->id), 403);

        return DB::transaction(function () use ($workspace, $host, $actor, $requestId, $ip): OperationJob {
            // Serialize clicks from different sessions and actors on this host.
            LocalHost::query()->whereKey($host->id)->lockForUpdate()->firstOrFail();
            $latest = $this->latest($workspace, $host);
            if ($latest !== null && ! $latest->status->isTerminal()) {
                return $latest;
            }

            $pin = parse_ini_file(base_path('installer/manifests/chrome-mirror.v1'), false, INI_SCANNER_RAW);
            abort_unless(is_array($pin) && ($pin['schema'] ?? null) === '2', 503);
            abort_unless(($pin['repo'] ?? null) === 'mohammadhejazirad/install-google-chrome-server', 503);
            abort_unless(($pin['branch'] ?? null) === 'main' && ($pin['platform'] ?? null) === 'linux64', 503);
            abort_unless(! isset($pin['version']) && ! isset($pin['sha256']) && ! isset($pin['commit']), 503);
            $job = app(RecordOperation::class)->record(new RecordOperationData(
                workspaceId: $workspace->id,
                actorId: $actor->id,
                type: 'package.install',
                idempotencyKey: 'chrome:'.$host->uuid.':'.($latest?->uuid ?? 'initial'),
                plan: ['package' => 'google-chrome', 'operation' => 'package.install', 'contract_version' => '1', 'host_uuid' => $host->uuid, 'channel' => 'latest_stable', 'repo' => $pin['repo'], 'branch' => $pin['branch'], 'platform' => $pin['platform']],
                mutatesHost: true,
                resourceType: 'local_host',
                resourceId: $host->id,
            ));
            app(AuditRecorder::class)->record($workspace, $actor, 'chrome.install_requested', 'local_host', $host->uuid,
                AuditResult::Success, ['host_executed' => false], $requestId, $ip, $job);

            return $job;
        });
    }

    public function latest(Workspace $workspace, LocalHost $host): ?OperationJob
    {
        return $this->jobs($workspace, $host)->first();
    }

    /** A newer request must not hide a browser that already passed render verification. */
    public function verifiedInstallation(Workspace $workspace, LocalHost $host): ?string
    {
        foreach ($this->jobs($workspace, $host)->limit(20)->get() as $job) {
            $path = $this->verifiedPath($job);
            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }

    private function jobs(Workspace $workspace, LocalHost $host)
    {
        return OperationJob::query()->where('workspace_id', $workspace->id)
            ->where('resource_type', 'local_host')->where('resource_id', $host->id)
            ->where('type', 'package.install')->where('plan->input->package', 'google-chrome')
            ->with('steps')->latest('id');
    }

    /** Only a completed worker verification may expose an executable path. Never use the requested plan. */
    public function verifiedPath(OperationJob $job): ?string
    {
        if ($job->type !== 'package.install' || ($job->plan['input']['package'] ?? null) !== 'google-chrome' || $job->status !== JobStatus::Succeeded || $job->finished_at === null) {
            return null;
        }
        $step = $job->steps->firstWhere('key', 'browser.verify');
        $evidence = $step?->checkpoint;
        if ($step?->status !== StepStatus::Succeeded || $step->finished_at === null || ! is_array($evidence)
            || ($evidence['schema_version'] ?? null) !== '1'
            || ($evidence['package'] ?? null) !== 'google-chrome'
            || ($evidence['render_verified'] ?? null) !== true
            || preg_match('/^[a-f0-9]{64}$/D', (string) ($evidence['sha256'] ?? '')) !== 1
            || preg_match('/^[0-9]+(?:\.[0-9]+){3}$/D', (string) ($evidence['version'] ?? '')) !== 1) {
            return null;
        }
        $plannedVersion = $job->plan['input']['version'] ?? null;
        $plannedSha = $job->plan['input']['sha256'] ?? null;
        if ($plannedVersion !== null || $plannedSha !== null) {
            if ($plannedVersion !== $evidence['version'] || $plannedSha !== $evidence['sha256']) {
                return null;
            }
        } elseif (($job->plan['input']['channel'] ?? null) !== 'latest_stable'
            || ($job->plan['input']['repo'] ?? null) !== 'mohammadhejazirad/install-google-chrome-server'
            || ($job->plan['input']['branch'] ?? null) !== 'main'
            || ($job->plan['input']['platform'] ?? null) !== 'linux64') {
            return null;
        }
        $path = '/opt/rahandaz/browsers/chrome/'.$evidence['version'].'/chrome-linux64/chrome';

        return ($evidence['executable_path'] ?? null) === $path ? $path : null;
    }
}
