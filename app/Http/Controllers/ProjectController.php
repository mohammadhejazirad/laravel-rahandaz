<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\CurrentWorkspace;
use App\Http\PaginatorPayload;
use App\Models\Environment;
use App\Models\Project;
use App\Models\ProjectSourceArchive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = CurrentWorkspace::get($request);

        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $query = trim($filters['q'] ?? '');
        $projects = Project::query()
            ->forWorkspace($workspace->id)
            ->when($query !== '', fn ($q) => $q->where(fn ($q) => $q->whereRaw('strpos(lower(name), lower(?)) > 0', [$query])->orWhereRaw('strpos(lower(slug), lower(?)) > 0', [$query])))
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
            'filters' => ['q' => $query],
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
        $archives = ProjectSourceArchive::query()
            ->where('project_id', $project->id)
            ->with([
                'latestDeployment.environment',
                'latestDeployment.operationJob',
            ])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

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
            'source_archives' => $archives->map(function (ProjectSourceArchive $archive): array {
                $deployment = $archive->latestDeployment;

                return [
                    'uuid' => $archive->uuid,
                    'original_name' => $archive->original_name,
                    'sha256' => $archive->sha256,
                    'size_bytes' => $archive->size_bytes,
                    'entry_count' => $archive->entry_count,
                    'uncompressed_bytes' => $archive->uncompressed_bytes,
                    'status' => $archive->status->value,
                    'created_at' => $archive->created_at?->utc()->toIso8601ZuluString(),
                    'deployment' => $deployment === null ? null : [
                        'uuid' => $deployment->uuid,
                        'status' => $deployment->status->value,
                        'environment_name' => $deployment->environment?->name,
                        'operation_job_uuid' => $deployment->operationJob?->uuid,
                    ],
                ];
            })->all(),
            'archive_upload' => [
                'available' => $request->user()?->can('deploy', $project) === true,
                'max_bytes' => (int) config('rahandaz.project_archives.max_upload_bytes'),
                'execution_mode' => (string) config('rahandaz.execution_mode'),
            ],
            'deploy_available' => false,
        ]);
    }
}
