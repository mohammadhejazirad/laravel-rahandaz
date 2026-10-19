<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Projects\Enums\ProjectType;
use App\Domain\Projects\Exceptions\ProjectLaunchRejected;
use App\Domain\Projects\ProjectSourceLaunch;
use App\Http\CurrentWorkspace;
use App\Http\WorkspaceAccess;
use App\Models\GitConnection;
use App\Models\LocalHost;
use App\Models\RemoteRepository;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProjectLaunchController extends Controller
{
    public function create(Request $request, ProjectSourceLaunch $launch): Response
    {
        $workspace = CurrentWorkspace::get($request);
        $this->assertManager($request, $workspace);
        $filters = $request->validate([
            'connection' => ['nullable', 'uuid'],
            'repository' => ['nullable', 'uuid'],
            'repo_q' => ['nullable', 'string', 'max:200'],
        ]);
        $search = trim($filters['repo_q'] ?? '');
        $connection = $this->connection($workspace->id, $filters['connection'] ?? null);
        $repositories = $connection === null ? ['data' => [], 'truncated' => false] : $launch->repositories($connection, $search);
        $repository = $this->repository($connection, $filters['repository'] ?? null);
        $branches = $repository === null ? [] : $repository->branches()->orderBy('name')->get()->map(fn ($branch): array => [
            'name' => $branch->name,
            'head_sha' => $branch->head_sha,
            'is_default' => $branch->name === $repository->default_branch,
        ])->all();

        return Inertia::render('projects/create', [
            'accounts' => $launch->accounts($workspace),
            'hosts' => LocalHost::query()->forWorkspace($workspace->id)->orderBy('display_name')->get()->map(fn (LocalHost $host): array => [
                'uuid' => $host->uuid,
                'name' => $host->display_name,
                'slug' => $host->slug,
            ])->all(),
            'connection' => $connection?->uuid,
            'repositories' => $repositories,
            'repository' => $repository === null ? null : [
                'uuid' => $repository->uuid,
                'full_name' => $repository->full_name,
                'default_branch' => $repository->default_branch,
                'suggested_name' => mb_substr(str_contains($repository->full_name, '/') ? substr($repository->full_name, (int) strrpos($repository->full_name, '/') + 1) : $repository->full_name, 0, 120),
            ],
            'branches' => $branches,
            'filters' => ['repo_q' => $search],
            'types' => array_map(fn (ProjectType $type): array => ['value' => $type->value, 'label' => $type->label()], ProjectType::cases()),
            'execution_mode' => (string) config('rahandaz.execution_mode'),
        ]);
    }

    public function branches(Request $request, RemoteRepository $repository, ProjectSourceLaunch $launch)
    {
        $workspace = CurrentWorkspace::get($request);
        $this->assertManager($request, $workspace);
        abort_unless((string) $repository->workspace_id === (string) $workspace->id, 404);
        $repository->loadMissing('gitConnection');
        try {
            $launch->prepareBranches($repository, $request->user(), $request->attributes->get('request_id'), $request->ip());
        } catch (ProjectLaunchRejected $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable) {
            return back()->with('error', 'فهرست شاخه‌ها دریافت نشد. اتصال حساب و دسترسی مخزن را بررسی کنید.');
        }

        return redirect()->route('projects.create', [
            'connection' => $repository->gitConnection?->uuid,
            'repository' => $repository->uuid,
        ])->with('info', 'شاخه‌های این مخزن دریافت شد. یکی را انتخاب کنید. این دریافت هنوز کدی را روی سرور کپی نکرده است.');
    }

    public function store(Request $request, ProjectSourceLaunch $launch)
    {
        $workspace = CurrentWorkspace::get($request);
        $this->assertManager($request, $workspace);
        $data = $request->validate([
            'repository' => ['required', 'uuid'],
            'branch' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:120'],
            'declared_type' => ['required', Rule::enum(ProjectType::class)],
            'host' => ['required', 'uuid'],
        ]);
        $repository = RemoteRepository::query()->forWorkspace($workspace->id)->where('uuid', $data['repository'])->first();
        $host = LocalHost::query()->forWorkspace($workspace->id)->where('uuid', $data['host'])->first();
        if ($repository === null || $host === null) {
            return back()->with('error', 'مخزن یا سرور در این فضای کاری پیدا نشد.');
        }

        try {
            $result = $launch->launch(
                $workspace,
                $request->user(),
                $host,
                $repository,
                $data['branch'],
                $data['name'],
                ProjectType::from($data['declared_type']),
                $request->attributes->get('request_id'),
                $request->ip(),
            );
        } catch (ProjectLaunchRejected $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable) {
            return back()->with('error', 'پروژه ثبت نشد. حساب، مخزن و شاخه را دوباره بررسی کنید.');
        }

        return redirect()->route('deployments.show', $result['deployment_uuid'])->with('info', 'درخواست استقرار ثبت شد. این ثبت به‌معنی کپی یا اجرای کد روی میزبان نیست. وضعیت را در همین صفحه دنبال کنید.');
    }

    private function assertManager(Request $request, Workspace $workspace): void
    {
        WorkspaceAccess::assertManagesSecrets($request->user(), $workspace);
    }

    private function connection(int|string $workspaceId, ?string $uuid): ?GitConnection
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        return GitConnection::query()->forWorkspace($workspaceId)->where('uuid', $uuid)->first();
    }

    private function repository(?GitConnection $connection, ?string $uuid): ?RemoteRepository
    {
        if ($connection === null || $uuid === null || $uuid === '') {
            return null;
        }

        return $connection->repositories()->where('uuid', $uuid)->where('disabled', false)->where('archived', false)->first();
    }
}
