<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LocalHost;
use App\Models\MetricSample;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_preserves_samples_and_pruning_obeys_exact_boundary(): void
    {
        $this->freezeTime();
        $host = LocalHost::factory()->create();
        foreach ([now()->subDays(8), now()->subDays(7), now()] as $time) {
            MetricSample::query()->create(['local_host_id' => $host->id, 'source' => 'host', 'collected_at' => $time, 'observed_at' => $time, 'cpu_percent' => 12]);
        }
        $this->artisan('rahandaz:metrics:prune --dry-run')->expectsOutput('expired=1')->assertSuccessful();
        $this->assertDatabaseCount('metric_samples', 3);
        $this->artisan('rahandaz:metrics:prune')->expectsOutput('deleted=1')->assertSuccessful();
        $this->assertDatabaseCount('metric_samples', 2);
        $this->artisan('rahandaz:metrics:prune')->expectsOutput('deleted=0')->assertSuccessful();
    }

    public function test_invalid_retention_is_rejected_without_deleting(): void
    {
        foreach (['0', '-1', '366', '7days', '1.5'] as $value) {
            $this->artisan('rahandaz:metrics:prune', ['--days' => $value])->assertFailed();
        }
    }
}
