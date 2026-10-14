<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Monitoring\MetricBoard;
use App\Http\CurrentWorkspace;
use App\Http\PaginatorPayload;
use App\Models\AlertEvent;
use App\Models\ManagedService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class MonitoringController extends Controller
{
    public function index(Request $request, MetricBoard $board): Response
    {
        $workspace = CurrentWorkspace::get($request);
        $services = ManagedService::query()->forWorkspace($workspace->id)->orderBy('kind')->paginate(20, pageName: 'services_page')->withQueryString();
        $alerts = AlertEvent::query()->forWorkspace($workspace->id)->orderByDesc('id')->paginate(20, pageName: 'alerts_page')->withQueryString();

        return Inertia::render('monitoring/index', [
            'board' => $board->forWorkspace($workspace), 'generated_at' => now()->utc()->toIso8601ZuluString(),
            'services' => ['data' => $services->getCollection()->map(fn (ManagedService $s): array => ['uuid' => $s->uuid, 'kind' => $s->kind->value, 'runtime_state' => $s->runtime_state->value, 'health_status' => $s->health_status->value, 'observed_at' => $s->observed_at?->utc()->toIso8601ZuluString(), 'stale' => $s->observed_at === null || $s->observed_at->lt(now()->subSeconds(MetricBoard::STALE_SECONDS))])->all(), 'meta' => PaginatorPayload::meta($services)],
            'alerts' => ['data' => $alerts->getCollection()->map(fn (AlertEvent $a): array => ['uuid' => $a->uuid, 'state' => $a->state->value, 'summary' => $a->summary, 'started_at' => $a->started_at?->utc()->toIso8601ZuluString()])->all(), 'meta' => PaginatorPayload::meta($alerts)],
        ]);
    }
}
