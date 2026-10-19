<?php

declare(strict_types=1);

namespace App\Domain\Projects;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Git\Enums\GitConnectionState;
use App\Domain\Git\Enums\GitProvider;
use App\Domain\Git\Exceptions\GitRejected;
use App\Domain\Git\GitConnector;
use App\Domain\Projects\Enums\DeploymentStrategy;
use App\Domain\Projects\Enums\EnvironmentStatus;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Enums\ProjectType;
use App\Domain\Projects\Exceptions\DeploymentRejected;
use App\Domain\Projects\Exceptions\ProjectLaunchRejected;
use App\Models\Environment;
use App\Models\GitConnection;
use App\Models\LocalHost;
use App\Models\Project;
use App\Models\RemoteBranch;
use App\Models\RemoteRepository;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ProjectSourceLaunch
{
    public function __construct(
        private readonly GitConnector $git,
        private readonly ProjectTypeDetector $types,
        private readonly DeploymentWorkflow $deployments,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @return list<array{uuid: string, provider: string, account_label: string, base_url: string, repository_count: int}>
     */
    public function accounts(Workspace $workspace): array
    {
        return GitConnection::query()
            ->forWorkspace($workspace->id)
            ->where('state', GitConnectionState::Active)
            ->withCount('repositories')
            ->orderByDesc('id')
            ->get()
            ->map(fn (GitConnection $connection): array => [
                'uuid' => $connection->uuid,
                'provider' => $this->providerKey($connection),
                'account_label' => $connection->account_label,
                'base_url' => $connection->base_url,
                'repository_count' => (int) $connection->repositories_count,
            ])
            ->all();
    }

    /**
     * @return array{data: list<array{uuid: string, full_name: string, default_branch: string, is_selected: bool}>, truncated: bool}
     */
    public function repositories(GitConnection $connection, string $search): array
    {
        $this->assertUsable($connection);
        $query = $connection->repositories()
            ->where('disabled', false)
            ->where('archived', false)
            ->when($search !== '', fn ($q) => $q->whereRaw('strpos(lower(full_name), lower(?)) > 0', [$search]))
            ->orderBy('full_name');
        $total = (clone $query)->count();

        return [
            'data' => $query->limit(200)->get()->map(fn (RemoteRepository $repository): array => [
                'uuid' => $repository->uuid,
                'full_name' => $repository->full_name,
                'default_branch' => $repository->default_branch,
                'is_selected' => $repository->is_selected,
            ])->all(),
            'truncated' => $total > 200,
        ];
    }

    /**
     * @return list<array{name: string, head_sha: string, is_default: bool}>
     */
    public function prepareBranches(RemoteRepository $repository, User $actor, ?string $requestId, ?string $sourceIp): array
    {
        $repository->loadMissing('gitConnection');
        $connection = $repository->gitConnection;
        if (! $connection instanceof GitConnection) {
            throw new ProjectLaunchRejected('اتصال این مخزن پیدا نشد.');
        }
        $this->assertUsable($connection);
        if ($repository->archived || $repository->disabled) {
            throw new ProjectLaunchRejected('این مخزن در دسترس نیست.');
        }

        $wasSelected = $repository->is_selected;
        if (! $wasSelected) {
            $repository->forceFill(['is_selected' => true])->save();
        }

        try {
            $this->git->syncBranches($repository->fresh() ?? $repository, $actor, $requestId, $sourceIp);
        } catch (GitRejected $exception) {
            if (! $wasSelected) {
                $repository->forceFill(['is_selected' => false])->save();
            }
            throw new ProjectLaunchRejected($exception->getMessage());
        } catch (Throwable $exception) {
            if (! $wasSelected) {
                $repository->forceFill(['is_selected' => false])->save();
            }
            throw $exception;
        }

        return $this->branchesOf($repository->fresh() ?? $repository);
    }

    public function suggestType(RemoteRepository $repository, User $actor, ?string $requestId, ?string $sourceIp): ?ProjectType
    {
        $repository->loadMissing('gitConnection.workspace');
        $connection = $repository->gitConnection;
        try {
            $snapshot = $this->git->rootMarkers($repository, $actor);
            $type = $this->types->detect($snapshot['names'], $snapshot['files']);
            $result = AuditResult::Success;
        } catch (GitRejected) {
            $type = null;
            $result = AuditResult::Failure;
        }

        if ($connection instanceof GitConnection && $connection->workspace !== null) {
            $this->audit->record(
                $connection->workspace,
                $actor,
                'project.type_hint',
                'remote_repository',
                $repository->uuid,
                $result,
                ['detected_type' => $type?->value ?? 'unknown'],
                $requestId,
                $sourceIp,
            );
        }

        return $type;
    }

    /**
     * @return array{project: Project, deployment_uuid: string}
     */
    public function launch(
        Workspace $workspace,
        User $actor,
        LocalHost $host,
        RemoteRepository $repository,
        string $branch,
        string $name,
        ProjectType $type,
        ?ProjectType $detected,
        ?string $requestId,
        ?string $sourceIp,
    ): array {
        $repository->loadMissing('gitConnection');
        $connection = $repository->gitConnection;
        if (! $connection instanceof GitConnection || (string) $connection->workspace_id !== (string) $workspace->id) {
            throw new ProjectLaunchRejected('مخزن در این فضای کاری نیست.');
        }
        $this->assertUsable($connection);
        if ((string) $host->workspace_id !== (string) $workspace->id) {
            throw new ProjectLaunchRejected('سرور در این فضای کاری نیست.');
        }
        if (preg_match('/^[A-Za-z0-9._\/-]{1,255}$/', $branch) !== 1 || str_contains($branch, '..') || str_starts_with($branch, '-')) {
            throw new ProjectLaunchRejected('نام شاخه معتبر نیست.');
        }

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw new ProjectLaunchRejected('نام پروژه را تا ۱۲۰ نویسه وارد کنید.');
        }

        try {
            return DB::transaction(function () use ($workspace, $actor, $host, $repository, $branch, $name, $type, $detected, $requestId, $sourceIp, $connection): array {
                $stored = RemoteBranch::query()
                    ->where('remote_repository_id', $repository->id)
                    ->where('name', $branch)
                    ->first();
                if ($stored === null || ! is_string($stored->head_sha) || $stored->head_sha === '') {
                    throw new ProjectLaunchRejected('این شاخه در فهرست تازهٔ مخزن نیست. شاخه را دوباره انتخاب کنید.');
                }
                if (! $repository->is_selected || $repository->disabled || $repository->archived) {
                    throw new ProjectLaunchRejected('دسترسی این مخزن در حساب گیت فعال نیست.');
                }

                $slug = $this->slug($repository->full_name, $workspace->id);
                $project = new Project;
                $project->forceFill([
                    'workspace_id' => $workspace->id,
                    'local_host_id' => $host->id,
                    'name' => $name,
                    'slug' => $slug,
                    'declared_type' => $type,
                    'detected_type' => $detected,
                    'remote_repository_id' => $repository->id,
                    'default_branch' => $branch,
                    'auto_deploy' => false,
                    'zero_downtime_requested' => false,
                    'status' => ProjectStatus::Draft,
                    'created_by' => $actor->id,
                ])->save();

                $environment = new Environment;
                $environment->forceFill([
                    'project_id' => $project->id,
                    'workspace_id' => $workspace->id,
                    'local_host_id' => $host->id,
                    'name' => 'تولید',
                    'slug' => 'production',
                    'branch_name' => $branch,
                    'release_path' => '/srv/rahandaz/projects/'.$slug.'/production/releases',
                    'shared_path' => '/srv/rahandaz/projects/'.$slug.'/production/shared',
                    'deployment_strategy' => DeploymentStrategy::SymlinkRelease,
                    'healthcheck_path' => '/health',
                    'status' => EnvironmentStatus::Draft,
                ])->save();

                $this->audit->record(
                    $workspace,
                    $actor,
                    'project.launch',
                    'project',
                    $project->uuid,
                    AuditResult::Success,
                    [
                        'host_executed' => false,
                        'provider' => $this->providerKey($connection),
                        'account_label' => $connection->account_label,
                        'repository' => $repository->full_name,
                        'branch' => $branch,
                        'commit_sha' => $stored->head_sha,
                    ],
                    $requestId,
                    $sourceIp,
                );

                $queued = $this->deployments->queueManual($environment, $actor, $stored->head_sha, $requestId, $sourceIp, 'launch:'.$project->uuid);

                return ['project' => $project, 'deployment_uuid' => $queued->deployment->uuid];
            });
        } catch (ProjectLaunchRejected $exception) {
            throw $exception;
        } catch (DeploymentRejected $exception) {
            throw new ProjectLaunchRejected($exception->getMessage());
        }
    }

    private function assertUsable(GitConnection $connection): void
    {
        if ($connection->state !== GitConnectionState::Active) {
            throw new ProjectLaunchRejected('این حساب گیت فعال نیست.');
        }
    }

    private function providerKey(GitConnection $connection): string
    {
        return $connection->provider === GitProvider::Gitlab && $connection->base_url !== 'https://gitlab.com'
            ? 'gitlabSelf'
            : $connection->provider->value;
    }

    /**
     * @return list<array{name: string, head_sha: string, is_default: bool}>
     */
    private function branchesOf(RemoteRepository $repository): array
    {
        return $repository->branches()->orderBy('name')->get()->map(fn (RemoteBranch $branch): array => [
            'name' => $branch->name,
            'head_sha' => $branch->head_sha,
            'is_default' => $branch->name === $repository->default_branch,
        ])->all();
    }

    private function slug(string $fullName, int|string $workspaceId): string
    {
        $leaf = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', str_contains($fullName, '/') ? (string) strrchr($fullName, '/') : $fullName));
        $base = trim($leaf, '-');
        if (strlen($base) < 2) {
            $base = 'app';
        }
        $base = substr($base, 0, 40);
        $slug = $base;
        for ($n = 2; Project::query()->where('workspace_id', $workspaceId)->where('slug', $slug)->exists(); $n++) {
            $suffix = '-'.$n;
            $slug = substr($base, 0, 49 - strlen($suffix)).$suffix;
            if ($n > 50) {
                throw new ProjectLaunchRejected('نام یکتای پروژه ساخته نشد. نام مخزن را کوتاه‌تر انتخاب کنید.');
            }
        }

        return $slug;
    }
}
