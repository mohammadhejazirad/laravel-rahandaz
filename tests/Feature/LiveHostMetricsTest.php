<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Monitoring\LiveHostMetrics;
use App\Infrastructure\Host\HostFileReader;
use App\Models\LocalHost;
use App\Models\MetricSample;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class LiveHostMetricsTest extends TestCase
{
    use CreatesPanelAccount, RefreshDatabase;

    private function files(int $step, int $uptime = 100): HostFileReader
    {
        $reader = $this->createMock(HostFileReader::class);
        $reader->method('read')->willReturnCallback(fn (string $path) => match ($path) {
            '/proc/stat' => 'cpu  '.(100 + $step * 30).' 0 20 '.(300 + $step * 50).' '.(10 + $step * 20)." 0 0 0 100 0\ncpu0  1 2 3 4\n",
            '/proc/uptime' => (string) $uptime.' 42',
            '/proc/meminfo' => "MemTotal: 2048 kB\nMemAvailable: 1536 kB\nSwapTotal: 1024 kB\nSwapFree: 768 kB\n",
            '/proc/loadavg' => '0.01 0.02 0.03 1/100 10',
            '/proc/net/dev' => 'eth0: '.(1000 + $step * 3000).' 0 0 0 0 0 0 0 '.(2000 + $step * 6000)." 0 0 0 0 0 0 0\nlo: 999999 0 0 0 0 0 0 0 999999 0 0 0 0 0 0 0\n",
            default => null,
        });

        return $reader;
    }

    public function test_actual_deltas_memory_available_shared_cache_and_persistence_throttle(): void
    {
        [, $workspace] = $this->panelAccount(setupComplete: true);
        LocalHost::factory()->create(['workspace_id' => $workspace->id, 'slug' => 'local-host']);
        Cache::flush();
        $this->freezeTime();
        $first = (new LiveHostMetrics($this->files(0)))->forWorkspace($workspace);
        $this->assertNull($first['current']['cpu_percent']);
        $this->assertNull($first['current']['network_rx_bytes_per_second']);
        $this->assertSame(512 * 1024, $first['current']['ram_used_bytes']);
        $this->assertSame(256 * 1024, $first['current']['swap_used_bytes']);
        $this->travel(3)->seconds();
        $next = (new LiveHostMetrics($this->files(1, 103)))->forWorkspace($workspace);
        $this->assertSame(30.0, $next['current']['cpu_percent']);
        $this->assertSame(1000, $next['current']['network_rx_bytes_per_second']);
        $this->assertSame(2000, $next['current']['network_tx_bytes_per_second']);
        $this->assertCount(2, $next['history']);
        $this->assertSame($next, (new LiveHostMetrics($this->files(500, 5000)))->forWorkspace($workspace));
        $this->assertDatabaseCount('metric_samples', 1);
        $this->travel(61)->seconds();
        $gap = (new LiveHostMetrics($this->files(3, 164)))->forWorkspace($workspace);
        $this->assertNull($gap['current']['cpu_percent']);
        $this->assertNull($gap['current']['network_rx_bytes_per_second']);
        $this->assertDatabaseCount('metric_samples', 2);
    }

    public function test_reset_missing_counters_and_history_are_never_fabricated(): void
    {
        [, $workspace] = $this->panelAccount(setupComplete: true);
        LocalHost::factory()->create(['workspace_id' => $workspace->id, 'slug' => 'local-host']);
        Cache::flush();
        $this->freezeTime();
        (new LiveHostMetrics($this->files(100, 1000)))->forWorkspace($workspace);
        $this->travel(3)->seconds();
        $reset = (new LiveHostMetrics($this->files(0, 1003)))->forWorkspace($workspace);
        $this->assertNull($reset['current']['cpu_percent']);
        $this->assertNull($reset['current']['network_rx_bytes_per_second']);
        $reader = $this->createMock(HostFileReader::class);
        $reader->method('read')->willReturn(null);
        $this->travel(3)->seconds();
        $missing = (new LiveHostMetrics($reader))->forWorkspace($workspace);
        $this->assertNull($missing['current']['ram_used_bytes']);
        $this->assertNull($missing['current']['load_1']);
        $state = Cache::get('metrics.host.v1');
        $state['history'] = array_fill(0, 120, $missing['current']);
        Cache::put('metrics.host.v1', $state);
        $this->travel(3)->seconds();
        $this->assertCount(120, (new LiveHostMetrics($this->files(1, 1100)))->forWorkspace($workspace)['history']);
    }

    public function test_workspace_without_local_host_receives_no_other_host_metrics(): void
    {
        [, $workspace] = $this->panelAccount(setupComplete: true);
        $result = (new LiveHostMetrics($this->files(0)))->forWorkspace($workspace);
        $this->assertNull($result['current']);
        $this->assertNotEmpty($result['error']);
        $this->assertSame([], $result['history']);
        $this->assertSame(0, MetricSample::query()->count());
    }
}
