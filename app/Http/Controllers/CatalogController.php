<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Access\OperationAccess;
use App\Domain\Catalog\ChromeInstallation;
use App\Http\CurrentWorkspace;
use App\Http\HostMutation;
use App\Http\PaginatorPayload;
use App\Models\LocalHost;
use App\Models\PackageDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = CurrentWorkspace::get($request);
        $host = LocalHost::query()->forWorkspace($workspace->id)->where('slug', 'local-host')->first();
        $chrome = app(ChromeInstallation::class);
        $job = $host === null ? null : $chrome->latest($workspace, $host);

        $packages = PackageDefinition::query()
            ->with('versions:id,package_definition_id,lifecycle')
            ->orderBy('display_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('catalog/index', [
            'packages' => [
                'data' => $packages->getCollection()->map(function (PackageDefinition $package): array {
                    $lifecycles = $package->versions
                        ->map(fn ($version): string => $version->lifecycle->value)
                        ->unique()
                        ->values()
                        ->all();

                    return [
                        'uuid' => $package->uuid,
                        'key' => $package->key,
                        'display_name' => $package->display_name,
                        'service_kind' => $package->service_kind->value,
                        'version_count' => $package->versions->count(),
                        'lifecycles' => $lifecycles,
                    ];
                })->all(),
                'meta' => PaginatorPayload::meta($packages),
            ],
            'install_available' => false,
            'chrome' => [
                'host_uuid' => $host?->uuid,
                'can_request' => $host !== null && app(OperationAccess::class)->managesWorkspace($request->user(), $workspace->id),
                'execution_available' => false,
                'job' => $job === null ? null : ['uuid' => $job->uuid, 'status' => $job->status->value, 'error_code' => $job->error_code],
                'executable_path' => $job === null ? null : $chrome->verifiedPath($job),
            ],
        ]);
    }

    public function installChrome(Request $request, LocalHost $localHost, ChromeInstallation $chrome): RedirectResponse
    {
        $workspace = CurrentWorkspace::get($request);
        abort_unless((string) $localHost->workspace_id === (string) $workspace->id, 404);
        abort_unless(app(OperationAccess::class)->managesWorkspace($request->user(), $workspace->id), 403);
        // No path, version, command, or download URL is accepted from the browser.
        $chrome->request($workspace, $localHost, $request->user(), $request->attributes->get('request_id'), $request->ip());

        return redirect()->route('catalog.index')->with('status', HostMutation::queuedMessage());
    }
}
