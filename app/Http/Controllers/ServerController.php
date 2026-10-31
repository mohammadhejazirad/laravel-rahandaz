<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Access\OperationAccess;
use App\Domain\Hosts\HostServiceCatalog;
use App\Domain\Hosts\HostServiceControl;
use App\Domain\Network\ServerLocationLookup;
use App\Http\CurrentWorkspace;
use App\Http\HostMutation;
use App\Http\PaginatorPayload;
use App\Models\LocalHost;
use Illuminate\Http\RedirectResponse;
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

    public function show(Request $request, LocalHost $localHost, ServerLocationLookup $location, HostServiceCatalog $services): Response
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
            'location' => $location->forPanel(),
            'services' => [
                'can_request' => $localHost->slug === 'local-host' && app(OperationAccess::class)->managesWorkspace($request->user(), $workspace->id),
                'execution_available' => false,
                'items' => $services->services(),
                'node_note' => 'Node.js سرویس systemd سراسری ندارد. برنامهٔ Node از استقرار همان پروژه یا از Docker همان پروژه مدیریت می‌شود.',
            ],
        ]);
    }

    public function service(Request $request, LocalHost $localHost, HostServiceControl $control): RedirectResponse
    {
        $workspace = CurrentWorkspace::get($request);
        $input = $request->validate([
            'unit' => ['required', 'string', 'max:128'],
            'action' => ['required', 'string', 'max:16'],
        ]);
        $job = $control->request($workspace, $localHost, $request->user(), $input['unit'], $input['action']);

        return redirect()->route('jobs.show', $job)->with('status', HostMutation::queuedMessage());
    }
}
