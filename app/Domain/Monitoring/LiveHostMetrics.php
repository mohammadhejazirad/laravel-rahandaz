<?php

declare(strict_types=1);

namespace App\Domain\Monitoring;

use App\Infrastructure\Host\HostFileReader;
use App\Models\LocalHost;
use App\Models\MetricSample;
use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class LiveHostMetrics
{
    public function __construct(private readonly HostFileReader $files) {}

    public function forWorkspace(Workspace $workspace): array
    {
        $host = LocalHost::query()->forWorkspace($workspace->id)->where('slug', 'local-host')->first();
        if ($host === null) {
            return ['current' => null, 'history' => [], 'error' => 'میزبان محلی هنوز ثبت نشده است.'];
        }
        try {
            // One physical host, one bounded snapshot, shared across tabs/workspaces.
            $state = Cache::get('metrics.host.v1', []);
            $lock = Cache::lock('metrics.host.v1.lock', 5);
            if (($state['sampled_at'] ?? 0) <= (float) now()->format('U.u') - 2 && $lock->get()) {
                try {
                    $state = Cache::get('metrics.host.v1', []);
                    if (($state['sampled_at'] ?? 0) <= (float) now()->format('U.u') - 2) {
                        $state = $this->sample($state);
                        Cache::put('metrics.host.v1', $state, 600);
                    }
                } finally {
                    $lock->release();
                }
            }
            $current = $state['current'] ?? null;
            if ($current !== null && ($state['sampled_at'] ?? 0) > (float) now()->format('U.u') - 15
                && Cache::add('metrics.persist.v1.'.$host->uuid, true, 60)) {
                MetricSample::query()->create(['local_host_id' => $host->id, 'source' => 'host', 'collected_at' => $current['collected_at'], 'observed_at' => $current['collected_at'],
                    'cpu_percent' => $current['cpu_percent'], 'ram_used_bytes' => $current['ram_used_bytes'], 'disk_used_bytes' => $current['disk_used_bytes'], 'load_1' => $current['load_1']]);
            }

            return ['current' => $current, 'history' => $state['history'] ?? [], 'error' => $current === null ? 'در انتظار نخستین مشاهدهٔ میزبان.' : ($state['error'] ?? null)];
        } catch (Throwable) {
            return ['current' => null, 'history' => [], 'error' => 'خواندن منابع سرور ممکن نشد؛ دسترسی proc و سرویس کش پنل را بررسی کنید. دریافت خودکار دوباره تلاش می‌کند.'];
        }
    }

    private function sample(array $previous): array
    {
        $timestamp = (float) now()->format('U.u');
        $uptime = $this->files->read('/proc/uptime');
        $seconds = is_string($uptime) && preg_match('/^(\d+(?:\.\d+)?)/', $uptime, $m) === 1 ? (float) $m[1] : null;
        $cpu = $this->cpu($this->files->read('/proc/stat'));
        $network = $this->network($this->files->read('/proc/net/dev'));
        $elapsed = $seconds !== null && isset($previous['uptime']) ? $seconds - $previous['uptime'] : null;
        $valid = $elapsed !== null && $elapsed >= 0.5 && $elapsed <= 30;
        $cpuPercent = null;
        if ($valid && $cpu !== null && isset($previous['cpu'])) {
            $total = $cpu['total'] - $previous['cpu']['total'];
            $idle = $cpu['idle'] - $previous['cpu']['idle'];
            if ($total > 0 && $idle >= 0 && $idle <= $total) {
                $cpuPercent = round(100 * ($total - $idle) / $total, 2);
            }
        }
        $memory = [];
        foreach (preg_split('/\R/', $this->files->read('/proc/meminfo') ?? '') ?: [] as $line) {
            if (preg_match('/^(MemTotal|MemAvailable|SwapTotal|SwapFree):\s+(\d+) kB/', $line, $m) === 1) {
                $memory[$m[1]] = (int) $m[2] * 1024;
            }
        }
        $loadText = $this->files->read('/proc/loadavg');
        $loads = preg_split('/\s+/', trim($loadText ?? '')) ?: [];
        $free = @disk_free_space('/');
        $total = @disk_total_space('/');
        $used = isset($memory['MemTotal'], $memory['MemAvailable']) ? max(0, $memory['MemTotal'] - $memory['MemAvailable']) : null;
        $rates = ['rx' => null, 'tx' => null];
        // Only matching interfaces are compared; ignore loopback and counter resets.
        if ($valid && $network !== null && isset($previous['network'])) {
            foreach (['rx', 'tx'] as $direction) {
                $delta = 0;
                $found = false;
                foreach ($network as $name => $counters) {
                    if (isset($previous['network'][$name]) && $counters[$direction] >= $previous['network'][$name][$direction]) {
                        $delta += $counters[$direction] - $previous['network'][$name][$direction];
                        $found = true;
                    }
                }
                $rates[$direction] = $found ? (int) round($delta / $elapsed) : null;
            }
        }
        $current = ['collected_at' => now()->utc()->toIso8601ZuluString(), 'cpu_percent' => $cpuPercent, 'cpu_cores' => $cpu['cores'] ?? null, 'interval_seconds' => $valid ? round($elapsed, 2) : null,
            'ram_used_bytes' => $used, 'ram_total_bytes' => $memory['MemTotal'] ?? null,
            'swap_used_bytes' => isset($memory['SwapTotal'], $memory['SwapFree']) ? max(0, $memory['SwapTotal'] - $memory['SwapFree']) : null, 'swap_total_bytes' => $memory['SwapTotal'] ?? null,
            'disk_used_bytes' => $total !== false && $free !== false ? (int) ($total - $free) : null, 'disk_total_bytes' => $total === false ? null : (int) $total,
            'load_1' => isset($loads[0]) && is_numeric($loads[0]) ? (float) $loads[0] : null, 'load_5' => isset($loads[1]) && is_numeric($loads[1]) ? (float) $loads[1] : null,
            'load_15' => isset($loads[2]) && is_numeric($loads[2]) ? (float) $loads[2] : null, 'uptime_seconds' => $seconds,
            'network_rx_bytes_per_second' => $rates['rx'], 'network_tx_bytes_per_second' => $rates['tx']];
        $history = array_slice([...($previous['history'] ?? []), $current], -120);

        return ['error' => $cpu === null || $seconds === null || ! isset($memory['MemTotal'], $memory['MemAvailable']) || $network === null ? 'بخشی از منابع سرور خوانده نشد. دسترسی فایل‌های proc را بررسی کنید؛ مقدار نامشخص با خط تیره نمایش داده می‌شود.' : null, 'sampled_at' => $timestamp, 'uptime' => $seconds, 'cpu' => $cpu, 'network' => $network, 'current' => $current, 'history' => $history];
    }

    private function cpu(?string $text): ?array
    {
        if ($text === null || preg_match('/^cpu\s+([\d ]+)$/m', $text, $m) !== 1) {
            return null;
        }
        $numbers = array_map('intval', preg_split('/\s+/', trim($m[1])) ?: []);
        if (count($numbers) < 4) {
            return null;
        }

        // guest/guest_nice are already included in user/nice; never count twice.
        return ['total' => array_sum(array_slice($numbers, 0, 8)), 'idle' => $numbers[3] + ($numbers[4] ?? 0), 'cores' => preg_match_all('/^cpu\d+\s/m', $text) ?: null];
    }

    private function network(?string $text): ?array
    {
        if ($text === null) {
            return null;
        }
        $interfaces = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (preg_match('/^\s*([^: ]+):\s+(.+)$/', $line, $m) === 1 && $m[1] !== 'lo') {
                $parts = preg_split('/\s+/', trim($m[2])) ?: [];
                if (count($parts) >= 16 && ctype_digit($parts[0]) && ctype_digit($parts[8])) {
                    $interfaces[$m[1]] = ['rx' => (int) $parts[0], 'tx' => (int) $parts[8]];
                }
            }
        }

        return $interfaces;
    }
}
