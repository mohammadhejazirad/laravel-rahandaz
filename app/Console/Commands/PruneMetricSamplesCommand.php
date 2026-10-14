<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Monitoring\MetricBoard;
use App\Models\MetricSample;
use Illuminate\Console\Command;

class PruneMetricSamplesCommand extends Command
{
    protected $signature = 'rahandaz:metrics:prune {--days=7 : Retention window, 1 to 365 days} {--dry-run : Count expired samples without deleting them}';

    protected $description = 'Delete stored metric samples older than the retention window';

    public function handle(): int
    {
        $input = (string) $this->option('days');
        if (preg_match('/^[1-9][0-9]{0,2}$/D', $input) !== 1 || (int) $input > 365) {
            $this->error('Retention days must be between 1 and 365.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays((int) $input);
        if ($this->option('dry-run')) {
            $this->line('expired='.MetricSample::query()->where('collected_at', '<', $cutoff)->count());

            return self::SUCCESS;
        }
        // Bound each transaction and the work per invocation on small VPS instances.
        $deleted = 0;
        for ($batch = 0; $batch < 100; $batch++) {
            $ids = MetricSample::query()->where('collected_at', '<', $cutoff)->orderBy('id')->limit(1000)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            $deleted += MetricSample::query()->whereIn('id', $ids)->where('collected_at', '<', $cutoff)->delete();
        }
        $this->line('deleted='.$deleted);
        $this->line('stale_seconds='.MetricBoard::STALE_SECONDS);
        $this->line('cutoff='.$cutoff->utc()->toIso8601ZuluString());

        return self::SUCCESS;
    }
}
