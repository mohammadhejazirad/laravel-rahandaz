<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Access\OperationAccess;
use App\Domain\Catalog\ChromeInstallation;
use App\Domain\Catalog\SoftwareCapability;
use App\Http\CurrentWorkspace;
use App\Http\HostMutation;
use App\Http\PaginatorPayload;
use App\Models\LocalHost;
use App\Models\PackageDefinition;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            'software' => $this->softwareState($request, $workspace, $host, ['software.install', 'setup.initialize']),
            'chrome' => [
                'host_uuid' => $host?->uuid,
                'can_request' => $host !== null && app(OperationAccess::class)->managesWorkspace($request->user(), $workspace->id),
                'execution_available' => false,
                'job' => $job === null ? null : ['uuid' => $job->uuid, 'status' => $job->status->value, 'error_code' => $job->error_code],
                'executable_path' => $host === null ? null : $chrome->verifiedInstallation($workspace, $host),
            ],
        ]);
    }

    public function installed(Request $request): Response
    {
        $workspace = CurrentWorkspace::get($request);
        $host = LocalHost::query()->forWorkspace($workspace->id)->where('slug', 'local-host')->first();

        return Inertia::render('catalog/installed', [
            'software' => $this->softwareState($request, $workspace, $host, ['software.upgrade']),
        ]);
    }

    public function installSoftware(Request $request, SoftwareCapability $software): RedirectResponse
    {
        $workspace = CurrentWorkspace::get($request);
        abort_unless(app(OperationAccess::class)->managesWorkspace($request->user(), $workspace->id), 403);
        $data = $request->validate(['packages' => ['required', 'array', 'min:1', 'max:32'], 'packages.*' => ['string', 'distinct'], 'idempotency_key' => ['required', 'uuid']]);
        $host = LocalHost::query()->forWorkspace($workspace->id)->where('slug', 'local-host')->firstOrFail();
        $software->request($workspace, $host, $request->user(), $data['packages'], $data['idempotency_key']);

        return redirect()->route('catalog.index');
    }

    public function upgradeSoftware(Request $request, SoftwareCapability $software): RedirectResponse
    {
        $workspace = CurrentWorkspace::get($request);
        abort_unless(app(OperationAccess::class)->managesWorkspace($request->user(), $workspace->id), 403);
        // The browser sends a catalog key only. Version strings, package names and URLs are not accepted.
        $data = $request->validate(['package' => ['required', 'string'], 'idempotency_key' => ['required', 'uuid']]);
        $host = LocalHost::query()->forWorkspace($workspace->id)->where('slug', 'local-host')->firstOrFail();
        $software->request($workspace, $host, $request->user(), [$data['package']], $data['idempotency_key'], 'software.upgrade');

        return redirect()->route('catalog.installed');
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

    private function softwareState(Request $request, Workspace $workspace, ?LocalHost $host, array $jobTypes): array
    {
        $software = app(SoftwareCapability::class);

        return [
            'request_key' => (string) Str::uuid(),
            'available' => $host !== null && $software->available(),
            'can_request' => app(OperationAccess::class)->managesWorkspace($request->user(), $workspace->id),
            'catalog' => $software->browserCatalog(),
            'categories' => $software->categories(),
            'installed' => $software->installed($workspace),
            'job' => $software->payload($software->latest($workspace, $jobTypes)),
            'active' => $software->payload($software->active($workspace)),
        ];
    }
}
