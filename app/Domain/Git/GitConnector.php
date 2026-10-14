<?php

declare(strict_types=1);

namespace App\Domain\Git;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Git\Enums\GitAuthMethod;
use App\Domain\Git\Enums\GitConnectionState;
use App\Domain\Git\Enums\GitProvider;
use App\Domain\Git\Enums\RepositoryVisibility;
use App\Domain\Git\Exceptions\GitRejected;
use App\Infrastructure\Http\Exceptions\UnsafeOutboundUrl;
use App\Infrastructure\Http\PublicHttpsGuard;
use App\Infrastructure\Secrets\CredentialStore;
use App\Models\GitConnection;
use App\Models\RemoteBranch;
use App\Models\RemoteRepository;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

final class GitConnector
{
    public function __construct(
        private readonly PublicHttpsGuard $guard,
        private readonly CredentialStore $credentials,
        private readonly AuditRecorder $audit,
    ) {}

    public function connect(
        Workspace $workspace,
        User $actor,
        GitProvider $provider,
        string $baseUrl,
        string $accessMaterial,
        string $accountType,
        ?string $requestId,
        ?string $sourceIp,
    ): GitConnection {
        $baseUrl = $this->baseUrl($provider, $baseUrl);
        $this->assertMaterial($accessMaterial);

        if (! in_array($accountType, ['user', 'organization', 'group'], true)) {
            throw new GitRejected('نوع حساب معتبر نیست.');
        }

        if ($provider === GitProvider::Github && $accountType === 'group') {
            throw new GitRejected('GitHub گروه GitLab ندارد.');
        }

        try {
            $identity = $this->identity($provider, $baseUrl, $accessMaterial);
        } catch (UnsafeOutboundUrl $exception) {
            throw new GitRejected($exception->getMessage());
        }

        try {
            return DB::transaction(function () use ($workspace, $actor, $provider, $baseUrl, $accessMaterial, $accountType, $identity, $requestId, $sourceIp): GitConnection {
                $credential = $this->credentials->store(
                    $workspace,
                    $actor,
                    $provider === GitProvider::Github ? 'github_access' : 'gitlab_access',
                    $accessMaterial,
                    $identity['login'],
                );
                $connection = GitConnection::query()->create([
                    'workspace_id' => $workspace->id,
                    'provider' => $provider,
                    'auth_method' => $provider === GitProvider::Github ? GitAuthMethod::PersonalAccessToken : GitAuthMethod::GitlabToken,
                    'account_label' => $identity['login'],
                    'external_account_id' => $identity['id'],
                    'external_account_type' => $accountType,
                    'credential_id' => $credential->id,
                    'base_url' => $baseUrl,
                    'scopes' => $identity['scopes'],
                    'state' => GitConnectionState::Active,
                    'expires_at' => null,
                    'last_synced_at' => null,
                    'revoked_at' => null,
                ]);
                $this->audit->record(
                    $workspace,
                    $actor,
                    'git.connect',
                    'git_connection',
                    $connection->uuid,
                    AuditResult::Success,
                    [
                        'provider' => $provider->value,
                        'account_label' => $identity['login'],
                        'base_url' => $baseUrl,
                        'webhook_registered' => false,
                    ],
                    $requestId,
                    $sourceIp,
                );

                return $connection;
            });
        } catch (Throwable $exception) {
            if (str_contains($exception->getMessage(), $accessMaterial)) {
                throw new GitRejected('اتصال ذخیره نشد.');
            }

            if (($exception instanceof QueryException) && ($exception->errorInfo[0] ?? null) === '23505') {
                throw new GitRejected('این حساب قبلاً در همین فضای کاری وصل شده است.');
            }

            throw $exception;
        }
    }

    public function syncRepositories(GitConnection $connection, User $actor, ?string $requestId, ?string $sourceIp): int
    {
        if ($connection->state === GitConnectionState::Revoked) {
            throw new GitRejected('اتصال باطل شده است.');
        }

        $connection->loadMissing('credential', 'workspace');
        $material = $this->credentials->openFor($connection->credential, $actor);

        try {
            $rows = $this->repositories($connection, $material);
        } catch (UnsafeOutboundUrl $exception) {
            throw new GitRejected($exception->getMessage());
        } catch (GitRejected $exception) {
            $connection->forceFill(['state' => GitConnectionState::Error])->save();

            throw $exception;
        }

        $stored = 0;

        DB::transaction(function () use ($connection, $actor, $rows, $requestId, $sourceIp, &$stored): void {
            foreach ($rows as $row) {
                if (! $this->cloneUrlAllowed($row['clone_url'])) {
                    continue;
                }

                RemoteRepository::query()->updateOrCreate(
                    [
                        'git_connection_id' => $connection->id,
                        'provider_repository_id' => $row['id'],
                    ],
                    [
                        'workspace_id' => $connection->workspace_id,
                        'full_name' => $row['full_name'],
                        'namespace' => $row['namespace'],
                        'clone_url' => $row['clone_url'],
                        'default_branch' => $row['default_branch'],
                        'visibility' => $row['visibility'],
                        'archived' => $row['archived'],
                        'disabled' => false,
                        'last_seen_at' => now(),
                        'provider_metadata' => [],
                    ],
                );
                $stored++;
            }

            $connection->forceFill([
                'state' => GitConnectionState::Active,
                'last_synced_at' => now(),
            ])->save();
            $this->audit->record(
                $connection->workspace,
                $actor,
                'git.sync',
                'git_connection',
                $connection->uuid,
                AuditResult::Success,
                [
                    'repository_count' => $stored,
                    'remote_mutated' => false,
                    'webhook_registered' => false,
                ],
                $requestId,
                $sourceIp,
            );
        });

        return $stored;
    }

    public function syncBranches(RemoteRepository $repository, User $actor, ?string $requestId, ?string $sourceIp): int
    {
        $repository->loadMissing('gitConnection.credential', 'gitConnection.workspace');
        $connection = $repository->gitConnection;

        if (! $connection instanceof GitConnection || $connection->state === GitConnectionState::Revoked) {
            throw new GitRejected('اتصال این مخزن قابل استفاده نیست.');
        }

        if ((string) $repository->workspace_id !== (string) $connection->workspace_id) {
            throw new GitRejected('مخزن خارج از اتصال است.');
        }

        $material = $this->credentials->openFor($connection->credential, $actor);

        try {
            $rows = $this->branches($connection, $repository, $material);
        } catch (UnsafeOutboundUrl $exception) {
            throw new GitRejected($exception->getMessage());
        }

        $stored = 0;

        DB::transaction(function () use ($repository, $connection, $actor, $rows, $requestId, $sourceIp, &$stored): void {
            foreach ($rows as $row) {
                RemoteBranch::query()->updateOrCreate(
                    [
                        'remote_repository_id' => $repository->id,
                        'name' => $row['name'],
                    ],
                    [
                        'head_sha' => $row['sha'],
                        'protected' => $row['protected'],
                        'observed_at' => now(),
                    ],
                );
                $stored++;
            }

            $this->audit->record(
                $connection->workspace,
                $actor,
                'git.branches',
                'remote_repository',
                $repository->uuid,
                AuditResult::Success,
                [
                    'branch_count' => $stored,
                    'remote_mutated' => false,
                ],
                $requestId,
                $sourceIp,
            );
        });

        return $stored;
    }

    public function disconnect(GitConnection $connection, User $actor, ?string $requestId, ?string $sourceIp): void
    {
        $connection->loadMissing('workspace');

        if ($connection->state === GitConnectionState::Revoked) {
            return;
        }

        $connection->forceFill([
            'state' => GitConnectionState::Revoked,
            'revoked_at' => now(),
        ])->save();
        $this->audit->record(
            $connection->workspace,
            $actor,
            'git.revoke',
            'git_connection',
            $connection->uuid,
            AuditResult::Success,
            [
                'remote_mutated' => false,
                'provider_revoked' => false,
            ],
            $requestId,
            $sourceIp,
        );
    }

    /**
     * @return array{id: string, login: string, scopes: list<string>}
     */
    private function identity(GitProvider $provider, string $baseUrl, string $material): array
    {
        if ($provider === GitProvider::Github) {
            $url = 'https://api.github.com/user';
            $response = $this->get($url, $this->guard->pin($url), $this->githubHeaders($material));
            $this->assertOk($response);
            $login = (string) ($response->json('login') ?? '');
            $id = (string) ($response->json('id') ?? '');
            $scopes = $this->scopes((string) $response->header('X-OAuth-Scopes'));
        } else {
            $url = $baseUrl.'/api/v4/user';
            $response = $this->get($url, $this->guard->pin($url), $this->gitlabHeaders($material));
            $this->assertOk($response);
            $login = (string) ($response->json('username') ?? '');
            $id = (string) ($response->json('id') ?? '');
            $scopes = ['read_api'];
        }

        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) !== 1 || preg_match('/^[A-Za-z0-9_.-]{1,120}$/', $login) !== 1) {
            throw new GitRejected('هویت برگشتی از provider قابل قبول نیست.');
        }

        return ['id' => $id, 'login' => $login, 'scopes' => $scopes];
    }

    /**
     * @return list<array{id: string, full_name: string, namespace: string, clone_url: string, default_branch: string, visibility: string, archived: bool}>
     */
    private function repositories(GitConnection $connection, string $material): array
    {
        if ($connection->provider === GitProvider::Github) {
            $url = 'https://api.github.com/user/repos';
            $response = $this->get($url, $this->guard->pin($url), $this->githubHeaders($material), [
                'per_page' => 100,
                'page' => 1,
            ]);
            $this->assertOk($response);
            $payload = $response->json();

            if (! is_array($payload)) {
                throw new GitRejected('فهرست مخزن GitHub قابل خواندن نیست.');
            }

            return $this->mapGithubRepositories($payload);
        }

        $url = rtrim($connection->base_url, '/').'/api/v4/projects';
        $response = $this->get($url, $this->guard->pin($url), $this->gitlabHeaders($material), [
            'membership' => 'true',
            'simple' => 'true',
            'per_page' => 100,
            'page' => 1,
        ]);
        $this->assertOk($response);
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new GitRejected('فهرست مخزن GitLab قابل خواندن نیست.');
        }

        return $this->mapGitlabRepositories($payload);
    }

    /**
     * @return list<array{name: string, sha: string, protected: bool}>
     */
    private function branches(GitConnection $connection, RemoteRepository $repository, string $material): array
    {
        if ($connection->provider === GitProvider::Github) {
            if (preg_match('/^[A-Za-z0-9_.-]+(\/[A-Za-z0-9_.-]+){1,5}$/', $repository->full_name) !== 1) {
                throw new GitRejected('نام مخزن برای خواندن شاخه مجاز نیست.');
            }

            $path = implode('/', array_map(rawurlencode(...), explode('/', $repository->full_name)));
            $url = 'https://api.github.com/repos/'.$path.'/branches';
            $response = $this->get($url, $this->guard->pin($url), $this->githubHeaders($material), ['per_page' => 100]);
        } else {
            if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $repository->provider_repository_id) !== 1) {
                throw new GitRejected('شناسه مخزن GitLab مجاز نیست.');
            }

            $url = rtrim($connection->base_url, '/').'/api/v4/projects/'.rawurlencode($repository->provider_repository_id).'/repository/branches';
            $response = $this->get($url, $this->guard->pin($url), $this->gitlabHeaders($material), ['per_page' => 100]);
        }

        $this->assertOk($response);
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new GitRejected('فهرست شاخه قابل خواندن نیست.');
        }

        $rows = [];

        foreach (array_slice($payload, 0, 100) as $branch) {
            if (! is_array($branch)) {
                continue;
            }

            $name = (string) ($branch['name'] ?? '');
            $sha = (string) ($connection->provider === GitProvider::Github
                ? ($branch['commit']['sha'] ?? '')
                : ($branch['commit']['id'] ?? ''));

            if (preg_match('/^[A-Za-z0-9._\/-]{1,255}$/', $name) !== 1 || str_contains($name, '..') || str_starts_with($name, '-')) {
                continue;
            }

            if (preg_match('/^[0-9a-f]{40}$/', $sha) !== 1 && preg_match('/^[0-9a-f]{64}$/', $sha) !== 1) {
                continue;
            }

            $rows[] = [
                'name' => $name,
                'sha' => $sha,
                'protected' => (bool) ($branch['protected'] ?? false),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $payload
     * @return list<array{id: string, full_name: string, namespace: string, clone_url: string, default_branch: string, visibility: string, archived: bool}>
     */
    private function mapGithubRepositories(array $payload): array
    {
        $rows = [];

        foreach (array_slice($payload, 0, 100) as $repository) {
            if (! is_array($repository)) {
                continue;
            }

            $fullName = (string) ($repository['full_name'] ?? '');
            $branch = (string) ($repository['default_branch'] ?? 'main');
            $id = (string) ($repository['id'] ?? '');
            $namespace = explode('/', $fullName)[0] ?? '';
            $visibility = ($repository['private'] ?? false) === true ? RepositoryVisibility::Private->value : RepositoryVisibility::Public->value;
            $row = $this->repositoryRow($id, $fullName, $namespace, (string) ($repository['clone_url'] ?? ''), $branch, $visibility, (bool) ($repository['archived'] ?? false));

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $payload
     * @return list<array{id: string, full_name: string, namespace: string, clone_url: string, default_branch: string, visibility: string, archived: bool}>
     */
    private function mapGitlabRepositories(array $payload): array
    {
        $rows = [];

        foreach (array_slice($payload, 0, 100) as $repository) {
            if (! is_array($repository)) {
                continue;
            }

            $fullName = (string) ($repository['path_with_namespace'] ?? '');
            $visibility = match ((string) ($repository['visibility'] ?? 'private')) {
                'public' => RepositoryVisibility::Public->value,
                'internal' => RepositoryVisibility::Internal->value,
                default => RepositoryVisibility::Private->value,
            };
            $namespace = is_array($repository['namespace'] ?? null)
                ? (string) ($repository['namespace']['path'] ?? '')
                : (explode('/', $fullName)[0] ?? '');
            $row = $this->repositoryRow(
                (string) ($repository['id'] ?? ''),
                $fullName,
                $namespace,
                (string) ($repository['http_url_to_repo'] ?? ''),
                (string) ($repository['default_branch'] ?? 'main'),
                $visibility,
                (bool) ($repository['archived'] ?? false),
            );

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array{id: string, full_name: string, namespace: string, clone_url: string, default_branch: string, visibility: string, archived: bool}|null
     */
    private function repositoryRow(string $id, string $fullName, string $namespace, string $cloneUrl, string $branch, string $visibility, bool $archived): ?array
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) !== 1) {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9_.-]+(\/[A-Za-z0-9_.-]+){1,5}$/', $fullName) !== 1) {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9_.-]{1,160}$/', $namespace) !== 1) {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9._\/-]{1,255}$/', $branch) !== 1 || str_contains($branch, '..')) {
            return null;
        }

        return [
            'id' => $id,
            'full_name' => $fullName,
            'namespace' => $namespace,
            'clone_url' => $cloneUrl,
            'default_branch' => $branch,
            'visibility' => $visibility,
            'archived' => $archived,
        ];
    }

    private function cloneUrlAllowed(string $url): bool
    {
        $row = DB::selectOne('select rahandaz_repository_clone_url(?) as ok', [$url]);

        return (bool) ($row->ok ?? false);
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, int|string>  $query
     */
    private function get(string $url, string $pinnedIp, array $headers, array $query = []): Response
    {
        if (! defined('CURLOPT_RESOLVE')) {
            throw new UnsafeOutboundUrl('پین کردن اتصال خروجی ممکن نیست.');
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $port = (int) (parse_url($url, PHP_URL_PORT) ?: 443);
        $address = str_contains($pinnedIp, ':') ? '['.$pinnedIp.']' : $pinnedIp;

        return Http::withOptions([
            'allow_redirects' => false,
            'curl' => [
                CURLOPT_RESOLVE => [$host.':'.$port.':'.$address],
            ],
        ])->withHeaders($headers)->timeout(8)->connectTimeout(3)->get($url, $query);
    }

    /**
     * @return array<string, string>
     */
    private function githubHeaders(string $material): array
    {
        return [
            'Authorization' => 'Bearer '.$material,
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'Rahandaz',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function gitlabHeaders(string $material): array
    {
        return [
            'PRIVATE-TOKEN' => $material,
            'Accept' => 'application/json',
            'User-Agent' => 'Rahandaz',
        ];
    }

    private function assertOk(Response $response): void
    {
        if ($response->status() === 401 || $response->status() === 403) {
            throw new GitRejected('provider این دسترسی را نپذیرفت.');
        }

        if ($response->status() !== 200) {
            throw new GitRejected('پاسخ provider قابل قبول نبود.');
        }
    }

    /**
     * @return list<string>
     */
    private function scopes(string $header): array
    {
        $scopes = [];

        foreach (explode(',', $header) as $scope) {
            $scope = trim($scope);

            if (preg_match('/^[A-Za-z0-9:_-]{1,40}$/', $scope) === 1) {
                $scopes[] = $scope;
            }
        }

        if ($scopes === []) {
            return ['unlisted'];
        }

        return array_slice(array_values(array_unique($scopes)), 0, 32);
    }

    private function baseUrl(GitProvider $provider, string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');

        if ($provider === GitProvider::Github) {
            if ($baseUrl !== 'https://github.com') {
                throw new GitRejected('نشانی GitHub باید https://github.com باشد.');
            }

            return $baseUrl;
        }

        try {
            $this->guard->pin($baseUrl);
        } catch (UnsafeOutboundUrl $exception) {
            throw new GitRejected($exception->getMessage());
        }

        return $baseUrl;
    }

    private function assertMaterial(string $material): void
    {
        if (strlen($material) < 8 || strlen($material) > 255 || preg_match('/\s/', $material) === 1) {
            throw new GitRejected('داده دسترسی قابل قبول نیست.');
        }
    }
}
