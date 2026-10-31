<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Access\OperationAccess;
use App\Domain\Docker\DockerAppCatalog;
use App\Domain\Docker\DockerInstallation;
use App\Domain\Docker\DockerStackControl;
use App\Http\CurrentWorkspace;
use App\Http\HostMutation;
use App\Models\LocalHost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DockerController extends Controller
{
    public function index(Request $request, DockerInstallation $docker, DockerAppCatalog $apps): Response
    {
        return Inertia::render('docker/index', [
            'installed' => $docker->present(),
            'app_count' => count($apps->all()),
        ]);
    }

    public function apps(Request $request, DockerInstallation $docker, DockerAppCatalog $apps): Response
    {
        $workspace = CurrentWorkspace::get($request);

        return Inertia::render('docker/apps', [
            'installed' => $docker->present(),
            'can_request' => $this->canRequest($request, $workspace->id),
            'apps' => array_map(fn (array $app): array => [
                'id' => $app['id'],
                'name' => $app['name'],
                'summary' => $app['summary'],
                'ports' => $app['ports'],
                'env' => $app['env'],
            ], $apps->all()),
        ]);
    }

    public function compose(Request $request, DockerInstallation $docker): Response
    {
        $workspace = CurrentWorkspace::get($request);

        return Inertia::render('docker/compose', [
            'installed' => $docker->present(),
            'can_request' => $this->canRequest($request, $workspace->id),
        ]);
    }

    public function store(Request $request, DockerStackControl $stacks): RedirectResponse
    {
        $workspace = CurrentWorkspace::get($request);
        $host = LocalHost::query()->where('workspace_id', $workspace->id)->where('slug', 'local-host')->firstOrFail();
        $input = $request->validate([
            'slug' => ['required', 'string', 'max:41'],
            'template' => ['nullable', 'string', 'max:40'],
            'compose' => ['nullable', 'string', 'max:32768'],
            'env' => ['nullable', 'array', 'max:40'],
            'env.*' => ['nullable', 'string', 'max:512'],
        ]);
        $job = $stacks->request(
            $workspace,
            $host,
            $request->user(),
            $input['slug'],
            $input['template'] ?? null,
            $input['compose'] ?? null,
            $input['env'] ?? [],
        );

        return redirect()->route('jobs.show', $job)->with('status', HostMutation::queuedMessage());
    }

    private function canRequest(Request $request, int|string $workspaceId): bool
    {
        $host = LocalHost::query()->where('workspace_id', $workspaceId)->where('slug', 'local-host')->first();

        return $host !== null && app(OperationAccess::class)->managesWorkspace($request->user(), $workspaceId);
    }
}
