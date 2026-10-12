<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\CurrentWorkspace;
use App\Http\PaginatorPayload;
use App\Models\Environment;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = CurrentWorkspace::get($request);

        $projects = Project::query()
            ->forWorkspace($workspace->id)
            ->withCount('environments')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('projects/index', [
            'projects' => [
                'data' => $projects->getCollection()->map(fn (Project $project): array => [
                    'uuid' => $project->uuid,
                    'name' => $project->name,
                    'slug' => $project->slug,
                    'status' => $project->status->value,
                    'declared_type' => $project->declared_type->value,
                    'environment_count' => (int) $project->environments_count,
                    'created_at' => $project->created_at?->utc()->toIso8601ZuluString(),
                ])->all(),
                'meta' => PaginatorPayload::meta($projects),
            ],
            'create_available' => false,
        ]);
    }

    public function show(Request $request, Project $project): JsonResponse|Response
    {
        $this->authorize('view', $project);
        $project->loadMissing('workspace');

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'uuid' => $project->uuid,
                'name' => $project->name,
                'workspace_uuid' => $project->workspace?->uuid,
            ]);
        }

        $workspace = CurrentWorkspace::get($request);
        abort_unless((string) $project->workspace_id === (string) $workspace->id, 404);

        $environments = $project->environments()->orderBy('name')->get();

        return Inertia::render('projects/show', [
            'project' => [
                'uuid' => $project->uuid,
                'name' => $project->name,
                'slug' => $project->slug,
                'status' => $project->status->value,
                'declared_type' => $project->declared_type->value,
                'default_branch' => $project->default_branch,
                'created_at' => $project->created_at?->utc()->toIso8601ZuluString(),
                'workspace_uuid' => $project->workspace?->uuid,
            ],
            'environments' => $environments->map(fn (Environment $environment): array => [
                'uuid' => $environment->uuid,
                'name' => $environment->name,
                'slug' => $environment->slug,
                'branch_name' => $environment->branch_name,
                'status' => $environment->status->value,
            ])->all(),
            'deploy_available' => false,
        ]);
    }
}
