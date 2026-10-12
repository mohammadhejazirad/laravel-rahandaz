<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\CurrentWorkspace;
use App\Http\PaginatorPayload;
use App\Models\LocalHost;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = CurrentWorkspace::get($request);

        $hosts = LocalHost::query()
            ->forWorkspace($workspace->id)
            ->orderBy('slug')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('servers/index', [
            'hosts' => [
                'data' => $hosts->getCollection()->map(fn (LocalHost $host): array => [
                    'uuid' => $host->uuid,
                    'slug' => $host->slug,
                    'display_name' => $host->display_name,
                    'state' => $host->state->value,
                    'compatibility' => $host->compatibility->value,
                    'last_seen_at' => $host->last_seen_at?->utc()->toIso8601ZuluString(),
                ])->all(),
                'meta' => PaginatorPayload::meta($hosts),
            ],
        ]);
    }

    public function show(Request $request, LocalHost $localHost): Response
    {
        $workspace = CurrentWorkspace::get($request);
        abort_unless((string) $localHost->workspace_id === (string) $workspace->id, 404);

        return Inertia::render('servers/show', [
            'host' => [
                'uuid' => $localHost->uuid,
                'slug' => $localHost->slug,
                'display_name' => $localHost->display_name,
                'hostname' => $localHost->hostname,
                'state' => $localHost->state->value,
                'compatibility' => $localHost->compatibility->value,
                'last_seen_at' => $localHost->last_seen_at?->utc()->toIso8601ZuluString(),
            ],
            'monitoring_available' => false,
        ]);
    }
}
