<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Jobs\JobLogger;
use App\Models\OperationJob;

final class SoftwareReport
{
    /** Accept only fresh, execution-bound, allowlisted root evidence, never raw stderr. */
    public function parse(array $output, OperationJob $job, array $packages): ?array
    {
        if (($output['schema_version'] ?? null) !== '1' || ($output['execution_id'] ?? null) !== $job->uuid
            || ($output['operation'] ?? null) !== $job->type || ! is_int($output['verified_at'] ?? null)
            || $output['verified_at'] > now()->timestamp + 5 || $output['verified_at'] < now()->subMinutes(5)->timestamp
            || ! is_array($output['packages'] ?? null) || ! array_is_list($output['packages'])
            || array_column($output['packages'], 'package') !== $packages) {
            return null;
        }
        $rows = [];
        foreach ($output['packages'] as $row) {
            if (! is_array($row) || ! array_key_exists('version', $row)) {
                return null;
            }
            $status = $row['status'] ?? (($row['verified'] ?? false) ? 'succeeded' : null);
            if (! in_array($status, ['queued', 'running', 'succeeded', 'failed', 'skipped'], true)
                || ! is_bool($row['verified'] ?? null) || ! is_bool($row['changed'] ?? null)
                || ($row['verified'] !== ($status === 'succeeded'))
                || ($row['version'] !== null && (! is_string($row['version']) || preg_match('/^[A-Za-z0-9.+:~_-]{1,120}$/D', $row['version']) !== 1))
                || ($status === 'succeeded' && $row['version'] === null)
                || (isset($row['phase']) && ! in_array($row['phase'], ['pending', 'preflight', 'dependency_plan', 'download', 'installation', 'verification', 'verified', 'failed'], true))
                || (isset($row['error_code']) && preg_match('/^[A-Z_]{1,64}$/D', (string) $row['error_code']) !== 1)) {
                return null;
            }
            $clean = array_intersect_key($row, array_flip(['package', 'version', 'verified', 'changed', 'error_code', 'phase']));
            $clean['status'] = $status;
            // Executable paths come only from a fixed catalog path with a pinned version.
            $entry = app(SoftwareCapability::class)->catalog()[$row['package']];
            $path = app(SoftwareCapability::class)->verifiedExecutablePath($entry, $row);
            if ($path !== null) {
                $clean['executable_path'] = $path;
            }
            $rows[] = $clean;
        }
        $events = [];
        $sequence = 0;
        foreach (array_slice(is_array($output['events'] ?? null) ? $output['events'] : [], -200) as $event) {
            if (! is_array($event) || ! is_int($event['sequence'] ?? null) || $event['sequence'] <= $sequence || $event['sequence'] > 1000
                || ! in_array($event['package'] ?? null, [null, ...$packages], true)
                || ! in_array($event['phase'] ?? '', ['control_plane', 'preflight', 'dependency_plan', 'download', 'installation', 'verification', 'verified', 'final_health', 'failed'], true)
                || (isset($event['error_code']) && preg_match('/^[A-Z_]{1,64}$/D', (string) $event['error_code']) !== 1)) {
                return null;
            }
            $sequence = $event['sequence'];
            $events[] = array_intersect_key($event, array_flip(['sequence', 'package', 'phase', 'error_code']));
        }

        return ['packages' => $rows, 'events' => $events];
    }

    public function checkpoint(OperationJob $job, array $report, array $extra = []): array
    {
        $step = $job->steps()->where('key', 'gate')->firstOrFail();
        $last = $step->checkpoint['event_sequence'] ?? 0;
        $capability = app(SoftwareCapability::class);
        foreach ($report['events'] as $event) {
            if ($event['sequence'] <= $last) {
                continue;
            }
            $label = $event['package'] === null ? 'نصب گروهی' : ($capability->catalog()[$event['package']]['name'] ?? $event['package']);
            $message = $label.': '.$capability->phaseLabel($event['phase']);
            if (! empty($event['error_code'])) {
                $message .= ' — '.$capability->errorMessage($event['error_code']).' ['.$event['error_code'].']';
            }
            app(JobLogger::class)->append($job, empty($event['error_code']) ? 'info' : 'error', $message, 'gate');
            $last = max($last, $event['sequence']);
        }

        return [...$extra, 'packages' => $report['packages'], 'event_sequence' => $last];
    }
}
