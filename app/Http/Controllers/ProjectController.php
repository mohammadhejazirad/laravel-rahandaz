<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Access\OperationAccess;
use App\Domain\Access\ProjectAccess;
use App\Domain\Projects\DockerLayout;
use App\Domain\Projects\DockerProjectControl;
use App\Http\CurrentWorkspace;
use App\Http\HostMutation;
use App\Http\PaginatorPayload;
use App\Http\WorkspaceAccess;
use App\Models\Environment;
use App\Models\Project;
use App\Models\ProjectLaravelSetting;
use App\Models\ProjectSourceArchive;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        $projects = app(ProjectAccess::class)->query($request->user(), $workspace->id)
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
            'create_available' => WorkspaceAccess::membership($request->user(), $workspace)->role->mayDecryptSecrets(),
            'filters' => ['q' => $query],
        ]);
    }

    public function show(Request $request, Project $project): JsonResponse|Response
    {
        $this->authorize('view', $project);
        $workspace = CurrentWorkspace::get($request);
        abort_unless((string) $project->workspace_id === (string) $workspace->id, 404);
        $project->loadMissing('workspace');

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'uuid' => $project->uuid,
                'name' => $project->name,
                'workspace_uuid' => $project->workspace?->uuid,
            ]);
        }

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
                'detected_type' => $project->detected_type?->value,
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
            'laravel' => $this->laravelPayload($request, $workspace, $project),
            'docker' => [
                'can_request' => app(OperationAccess::class)->managesWorkspace($request->user(), $workspace->id) && ! str_starts_with($project->slug, 'rahandaz'),
                'execution_available' => false,
                'layouts' => [
                    ['id' => DockerLayout::COMPOSE_ONLY, 'label' => 'فقط Compose', 'detail' => 'یک فایل yml، بدون Dockerfile'],
                    ['id' => DockerLayout::DOCKERFILE_ONLY, 'label' => 'فقط Dockerfile', 'detail' => 'ساخت تصویر، بدون Compose'],
                    ['id' => DockerLayout::BOTH, 'label' => 'هر دو', 'detail' => 'Compose و Dockerfile با هم'],
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function laravelPayload(Request $request, Workspace $workspace, Project $project): ?array
    {
        if ($project->declared_type->value !== 'laravel' && $project->detected_type?->value !== 'laravel') {
            return null;
        }
        $row = ProjectLaravelSetting::query()->where('project_id', $project->id)->first();

        return [
            'can_request' => app(OperationAccess::class)->managesWorkspace($request->user(), $workspace->id),
            'schedule_enabled' => (bool) ($row->schedule_enabled ?? false),
            'horizon_enabled' => (bool) ($row->horizon_enabled ?? false),
            'reverb_enabled' => (bool) ($row->reverb_enabled ?? false),
            'reverb_host' => $row->reverb_host ?? '127.0.0.1',
            'reverb_port' => $row->reverb_port ?? 8080,
            'octane_enabled' => (bool) ($row->octane_enabled ?? false),
            'octane_server' => $row->octane_server ?? 'swoole',
            'octane_host' => $row->octane_host ?? '127.0.0.1',
            'octane_port' => $row->octane_port ?? 8000,
            'octane_workers' => $row->octane_workers ?? 2,
            'inertia_ssr_enabled' => (bool) ($row->inertia_ssr_enabled ?? false),
            'inertia_ssr_path' => $row->inertia_ssr_path ?? 'bootstrap/ssr/ssr.js',
            'maintenance_enabled' => (bool) ($row->maintenance_enabled ?? false),
        ];
    }

    public function docker(Request $request, Project $project, DockerProjectControl $control): RedirectResponse
    {
        $this->authorize('view', $project);
        $workspace = CurrentWorkspace::get($request);
        $input = $request->validate([
            'layout' => ['required', 'string', 'max:32'],
            'action' => ['required', 'string', 'max:16'],
            'filenames' => ['nullable', 'array', 'max:40'],
            'filenames.*' => ['string', 'max:80'],
            'service' => ['nullable', 'string', 'max:63'],
            'compose_file' => ['nullable', 'string', 'max:40'],
            'dockerfile' => ['nullable', 'string', 'max:48'],
        ]);
        $job = $control->request(
            $workspace,
            $project,
            $request->user(),
            $input['layout'],
            $input['action'],
            array_values($input['filenames'] ?? []),
            $input['service'] ?? null,
            $input['compose_file'] ?? null,
            $input['dockerfile'] ?? null,
        );

        return redirect()->route('jobs.show', $job)->with('status', HostMutation::queuedMessage());
    }
}
