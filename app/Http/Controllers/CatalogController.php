<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\CurrentWorkspace;
use App\Http\PaginatorPayload;
use App\Models\PackageDefinition;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        CurrentWorkspace::get($request);

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
        ]);
    }
}
