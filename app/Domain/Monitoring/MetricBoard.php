<?php

declare(strict_types=1);

namespace App\Domain\Monitoring;

use App\Models\LocalHost;
use App\Models\MetricSample;
use App\Models\Workspace;
use Illuminate\Support\Collection;

final class MetricBoard
{
    public const STALE_SECONDS = 180;

    /**
     * @return array{hosts: list<array<string, mixed>>, samples: list<array<string, mixed>>, series: list<array{host_uuid: ?string, points: list<array{collected_at: ?string, cpu_percent: float}>}>}
     */
    public function forWorkspace(Workspace $workspace): array
    {
        $hosts = LocalHost::query()->forWorkspace($workspace->id)->orderBy('slug')->get();
        $hostIds = $hosts->pluck('id');
        $samples = MetricSample::query()
            ->whereIn('local_host_id', $hostIds)
            ->orderByDesc('collected_at')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        $series = $samples
            ->filter(fn (MetricSample $sample): bool => $sample->source->value === 'host' && $sample->cpu_percent !== null)
            ->groupBy('local_host_id')
            ->map(function (Collection $rows, $hostId) use ($hosts): array {
                $points = $rows->sortBy(fn (MetricSample $s) => $s->collected_at?->getTimestamp() ?? 0)->values()->take(-24);

                return ['host_uuid' => $hosts->firstWhere('id', $hostId)?->uuid,
                    'points' => $points->count() < 2 ? [] : $points->map(fn (MetricSample $s): array => ['collected_at' => $s->collected_at?->utc()->toIso8601ZuluString(), 'cpu_percent' => (float) $s->cpu_percent])->values()->all()];
            })->values();

        return [
            'hosts' => $hosts->map(fn (LocalHost $host): array => [
                'uuid' => $host->uuid,
                'slug' => $host->slug,
                'display_name' => $host->display_name,
                'state' => $host->state->value,
            ])->all(),
            'samples' => $samples->map(fn (MetricSample $sample): array => $this->present($sample, $hosts))->all(),
            'series' => $series->all(),
        ];
    }

    /**
     * @param  Collection<int, LocalHost>  $hosts
     * @return array<string, mixed>
     */
    private function present(MetricSample $sample, Collection $hosts): array
    {
        $collectedAt = $sample->collected_at;
        $age = $collectedAt === null ? null : now()->getTimestamp() - $collectedAt->getTimestamp();

        return [
            'uuid' => $sample->uuid,
            'host_slug' => $hosts->firstWhere('id', $sample->local_host_id)?->slug,
            'source' => $sample->source->value,
            'collected_at' => $collectedAt?->utc()->toIso8601ZuluString(),
            'observed_at' => $sample->observed_at?->utc()->toIso8601ZuluString(),
            'cpu_percent' => $sample->cpu_percent === null ? null : (float) $sample->cpu_percent,
            'ram_used_bytes' => $sample->ram_used_bytes,
            'disk_used_bytes' => $sample->disk_used_bytes,
            'load_1' => $sample->load_1 === null ? null : (float) $sample->load_1,
            'healthy' => $sample->healthy,
            'stale' => $age === null || $age < -30 || $age > self::STALE_SECONDS,
        ];
    }
}
