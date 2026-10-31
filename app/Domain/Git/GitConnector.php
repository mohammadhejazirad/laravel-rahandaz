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
use App\Models\GitProviderSetting;
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
        private readonly GitOAuthClient $oauth,
    ) {}

    /** @return array{account_label: string, external_account_id: string} */
    public function testAccess(GitProvider $provider, string $baseUrl, string $material, bool $oauth = false, ?string $installationId = null): array
    {
        $baseUrl = $this->baseUrl($provider, $baseUrl);
        if (! $oauth) {
            $this->assertMaterial($material);
        }
        $identity = $this->identity($provider, $baseUrl, $material, $oauth);
        $connection = new GitConnection;
        $connection->provider = $provider;
        $connection->auth_method = $oauth ? GitAuthMethod::Oauth : GitAuthMethod::PersonalAccessToken;
        $url = $provider === GitProvider::Github
            ? ($installationId !== null ? 'https://api.github.com/user/installations/'.$installationId.'/repositories' : 'https://api.github.com/user/repos')
            : $baseUrl.'/api/v4/projects';
        if ($installationId !== null && preg_match('/^[0-9]{1,32}$/D', $installationId) !== 1) {
            throw new GitRejected('شناسه نصب معتبر نیست.');
        }
        $response = $this->get($url, $this->guard->pin($url), $this->connectionHeaders($connection, $material), ['per_page' => 1, 'membership' => 'true']);
        $this->assertOk($response);
        $payload = $installationId !== null ? $response->json('repositories') : $response->json();
        if (! is_array($payload) || ! array_is_list($payload)) {
            throw new GitRejected('پاسخ فهرست مخزن معتبر نیست.');
        }

        return ['account_label' => $identity['login'], 'external_account_id' => $identity['id']];
    }

    public function testAuthorizationPage(GitProviderSetting $setting): void
    {
        $url = $this->oauth->authorizeUrl($setting, bin2hex(random_bytes(32)), bin2hex(random_bytes(32)));
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $url = explode('?', $url, 2)[0];
        $response = $this->get($url, $this->guard->pin($url), ['Accept' => 'text/html'], $query);
        if (! in_array($response->status(), [200, 302, 303], true)) {
            throw new GitRejected('صفحهٔ مجوز سرویس در دسترس نیست.');
        }
    }

    public function connect(
        Workspace $workspace,
        User $actor,
        GitProvider $provider,
        string $baseUrl,
        string $accessMaterial,
        string $accountType,
        ?string $requestId,
        ?string $sourceIp,
        ?array $oauthTokens = null,
        ?string $installationId = null,
    ): GitConnection {
        $baseUrl = $this->baseUrl($provider, $baseUrl);
        if ($oauthTokens === null) {
            $this->assertMaterial($accessMaterial);
        }

        if (! in_array($accountType, ['user', 'organization', 'group'], true)) {
            throw new GitRejected('نوع حساب معتبر نیست.');
        }

        if ($provider === GitProvider::Github && $accountType === 'group') {
            throw new GitRejected('GitHub گروه GitLab ندارد.');
        }

        try {
            $identity = $this->identity($provider, $baseUrl, $accessMaterial, $oauthTokens !== null);
            if ($oauthTokens !== null && $provider === GitProvider::Github) {
                if (preg_match('/^[0-9]{1,32}$/D', (string) $installationId) !== 1) {
                    throw new GitRejected('شناسه نصب GitHub معتبر نیست.');
                }
                $setting = GitProviderSetting::query()->where('workspace_id', $workspace->id)->where('provider', 'github')->firstOrFail();
                $installation = null;
                for ($page = 1; $page <= 20; $page++) {
                    $url = 'https://api.github.com/user/installations';
                    $response = $this->get($url, $this->guard->pin($url), $this->githubHeaders($accessMaterial), ['per_page' => 100, 'page' => $page]);
                    $this->assertOk($response);
                    $items = $response->json('installations');
                    if (! is_array($items)) {
                        throw new GitRejected('نصب برنامه قابل بررسی نیست.');
                    }
                    foreach ($items as $item) {
                        if (is_array($item) && (string) ($item['id'] ?? '') === $installationId && ($item['app_slug'] ?? '') === $setting->app_slug) {
                            $installation = $item;
                            break 2;
                        }
                    }
                    if (count($items) < 100) {
                        break;
                    }
                }
                if ($installation === null || ($installation['permissions']['contents'] ?? '') !== 'read' || ($installation['permissions']['metadata'] ?? '') !== 'read' || in_array('write', $installation['permissions'] ?? [], true)) {
                    throw new GitRejected('نصب برنامه تأیید نشد؛ دسترسی Contents و Metadata باید فقط‌خواندنی باشد و مجوز نوشتن فعال نباشد.');
                }
                $account = $installation['account'] ?? [];
                if (preg_match('/^[A-Za-z0-9_-]{1,64}$/D', (string) ($account['id'] ?? '')) !== 1 || preg_match('/^[A-Za-z0-9_.-]{1,120}$/D', (string) ($account['login'] ?? '')) !== 1) {
                    throw new GitRejected('هویت مالک نصب GitHub معتبر نیست.');
                }
                $identity['id'] = (string) $account['id'];
                $identity['login'] = $account['login'];
                $identity['scopes'] = ['contents:read', 'metadata:read'];
                $accountType = ($account['type'] ?? '') === 'Organization' ? 'organization' : 'user';
                $url = 'https://api.github.com/user/installations/'.$installationId.'/repositories';
                $this->assertOk($this->get($url, $this->guard->pin($url), $this->githubHeaders($accessMaterial), ['per_page' => 1]));
            }
        } catch (UnsafeOutboundUrl $exception) {
            throw new GitRejected($exception->getMessage());
        }

        try {
            return DB::transaction(function () use ($workspace, $actor, $provider, $baseUrl, $accessMaterial, $accountType, $identity, $requestId, $sourceIp, $oauthTokens, $installationId): GitConnection {
                $credential = $this->credentials->store(
                    $workspace,
                    $actor,
                    $provider === GitProvider::Github ? 'github_access' : 'gitlab_access',
                    $oauthTokens === null ? $accessMaterial : json_encode($oauthTokens, JSON_THROW_ON_ERROR),
                    $identity['login'],
                );
                $existing = GitConnection::query()->where('workspace_id', $workspace->id)->where('provider', $provider)->where('base_url', $baseUrl)->where('external_account_id', $identity['id'])->lockForUpdate()->first();
                if ($existing !== null && $existing->state !== GitConnectionState::Revoked) {
                    throw new GitRejected('این حساب متصل است؛ برای اتصال مجدد ابتدا دسترسی آن را در پنل قطع کنید.');
                }
                $connection = $existing ?? new GitConnection;
                $connection->fill([
                    'workspace_id' => $workspace->id,
                    'provider' => $provider,
                    'auth_method' => $oauthTokens !== null ? ($provider === GitProvider::Github ? GitAuthMethod::GithubApp : GitAuthMethod::Oauth) : ($provider === GitProvider::Github ? GitAuthMethod::PersonalAccessToken : GitAuthMethod::GitlabToken),
                    'account_label' => $identity['login'],
                    'external_account_id' => $identity['id'],
                    'external_account_type' => $accountType,
                    'credential_id' => $credential->id,
                    'base_url' => $baseUrl,
                    'scopes' => $identity['scopes'],
                    'state' => GitConnectionState::Active,
                    'expires_at' => ! empty($oauthTokens['expires_in']) ? now()->addSeconds($oauthTokens['expires_in']) : null,
                    'installation_id' => $installationId,
                    'last_synced_at' => null,
                    'revoked_at' => null,
                ])->save();
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
        $credentialId = $connection->credential_id;
        $material = $this->oauth->accessToken($connection, $actor);

        try {
            $rows = $this->repositories($connection, $material, $actor);
        } catch (UnsafeOutboundUrl $exception) {
            throw new GitRejected($exception->getMessage());
        } catch (GitRejected $exception) {
            GitConnection::query()->whereKey($connection->id)->where('credential_id', $credentialId)->where('state', '!=', GitConnectionState::Revoked)->update(['state' => GitConnectionState::Error]);

            throw $exception;
        }

        $stored = 0;

        DB::transaction(function () use ($connection, $actor, $rows, $requestId, $sourceIp, $credentialId, &$stored): void {
            GitConnection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();
            $connection->refresh();
            if ($connection->state === GitConnectionState::Revoked || (string) $connection->credential_id !== (string) $credentialId) {
                throw new GitRejected('اتصال باطل شده است.');
            }
            $selected = $connection->repositories()->where('is_selected', true)->pluck('provider_repository_id')->all();
            $connection->repositories()->update(['disabled' => true, 'is_selected' => false]);
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
                        'is_selected' => ! $row['archived'] && in_array($row['id'], $selected, true),
                        'last_seen_at' => now(),
                        'provider_metadata' => [],
                        'provider_activity_at' => $row['provider_activity_at'],
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
        if (! $repository->is_selected || $repository->disabled || $repository->archived) {
            throw new GitRejected('دسترسی این مخزن انتخاب نشده یا مخزن در دسترس نیست.');
        }
        $repository->loadMissing('gitConnection.credential', 'gitConnection.workspace');
        $connection = $repository->gitConnection;

        if (! $connection instanceof GitConnection || $connection->state === GitConnectionState::Revoked) {
            throw new GitRejected('اتصال این مخزن قابل استفاده نیست.');
        }

        if ((string) $repository->workspace_id !== (string) $connection->workspace_id) {
            throw new GitRejected('مخزن خارج از اتصال است.');
        }

        $material = $this->oauth->accessToken($connection, $actor);

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

    /**
     * Root names plus a few small manifests from the default branch.
     * File bodies are not logged. Missing access returns an empty snapshot.
     *
     * @return array{names: list<string>, files: array<string, string>}
     */
    public function rootMarkers(RemoteRepository $repository, User $actor): array
    {
        $repository->loadMissing('gitConnection.credential', 'gitConnection.workspace');
        $connection = $repository->gitConnection;
        if (! $connection instanceof GitConnection || $connection->state === GitConnectionState::Revoked) {
            throw new GitRejected('اتصال این مخزن قابل استفاده نیست.');
        }
        if ((string) $repository->workspace_id !== (string) $connection->workspace_id) {
            throw new GitRejected('مخزن خارج از اتصال است.');
        }

        $branch = $repository->default_branch;
        if (preg_match('/^[A-Za-z0-9._\/-]{1,255}$/', $branch) !== 1 || str_contains($branch, '..') || str_starts_with($branch, '-')) {
            throw new GitRejected('شاخهٔ پیش‌فرض این مخزن مجاز نیست.');
        }

        $material = $this->oauth->accessToken($connection, $actor);
        $headers = $this->connectionHeaders($connection, $material);
        $listing = $this->markerResponse($this->markerUrl($connection, $repository, null), $headers, ['ref' => $branch, 'per_page' => 100]);
        if ($listing === null || ! array_is_list($listing)) {
            return ['names' => [], 'files' => []];
        }

        $names = [];
        foreach (array_slice($listing, 0, 100) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $name = (string) ($entry['name'] ?? '');
            if (preg_match('/^[A-Za-z0-9._-]{1,80}$/', $name) === 1) {
                $names[] = $name;
            }
        }
        $names = array_values(array_unique($names));
        $files = [];
        foreach (['composer.json', 'package.json', 'requirements.txt', 'pyproject.toml', 'Pipfile'] as $name) {
            if (! in_array($name, $names, true)) {
                continue;
            }
            $payload = $this->markerResponse($this->markerUrl($connection, $repository, $name), $headers, ['ref' => $branch]);
            $text = $this->markerText($payload);
            if ($text !== null) {
                $files[$name] = $text;
            }
        }

        return ['names' => $names, 'files' => $files];
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
    private function identity(GitProvider $provider, string $baseUrl, string $material, bool $oauth = false): array
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
            $response = $this->get($url, $this->guard->pin($url), $oauth ? ['Authorization' => 'Bearer '.$material, 'Accept' => 'application/json'] : $this->gitlabHeaders($material));
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
     * @return list<array{id: string, full_name: string, namespace: string, clone_url: string, default_branch: string, visibility: string, archived: bool, provider_activity_at: ?string}>
     */
    private function repositories(GitConnection $connection, string $material, User $actor): array
    {
        $github = $connection->provider === GitProvider::Github;
        $installation = $github && $connection->auth_method === GitAuthMethod::GithubApp;
        if ($installation && preg_match('/^[0-9]{1,32}$/D', (string) $connection->installation_id) !== 1) {
            throw new GitRejected('شناسه نصب GitHub معتبر نیست.');
        }
        $url = $github
            ? ($installation ? 'https://api.github.com/user/installations/'.$connection->installation_id.'/repositories' : 'https://api.github.com/user/repos')
            : rtrim($connection->base_url, '/').'/api/v4/projects';
        $rows = [];
        for ($page = 1; $page <= 100; $page++) {
            $query = ['per_page' => 100, 'page' => $page];
            if (! $github) {
                $query += ['membership' => 'true', 'simple' => 'true'];
            }
            $response = $this->get($url, $this->guard->pin($url), $this->connectionHeaders($connection, $material), $query);
            $this->assertOk($response);
            $payload = $installation ? $response->json('repositories') : $response->json();
            if (! is_array($payload) || ! array_is_list($payload)) {
                throw new GitRejected('فهرست مخزن سرویس قابل خواندن نیست.');
            }
            $this->audit->record($connection->workspace, $actor, 'git.repositories_page', 'git_connection', $connection->uuid, AuditResult::Success, ['page' => $page, 'repository_count' => count($payload)]);
            $rows = array_merge($rows, $github ? $this->mapGithubRepositories($payload) : $this->mapGitlabRepositories($payload));
            if (count($payload) < 100) {
                return $rows;
            }
        }
        throw new GitRejected('فهرست بیش از ۱۰۰۰۰ مخزن دارد؛ مجوز سرویس را محدودتر کنید.');
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
            $response = $this->get($url, $this->guard->pin($url), $this->connectionHeaders($connection, $material), ['per_page' => 100]);
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
     * @return list<array{id: string, full_name: string, namespace: string, clone_url: string, default_branch: string, visibility: string, archived: bool, provider_activity_at: ?string}>
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
                $rows[] = [...$row, 'provider_activity_at' => RepositoryActivity::latest($repository)];
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $payload
     * @return list<array{id: string, full_name: string, namespace: string, clone_url: string, default_branch: string, visibility: string, archived: bool, provider_activity_at: ?string}>
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
                $rows[] = [...$row, 'provider_activity_at' => RepositoryActivity::latest($repository)];
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

    private function markerUrl(GitConnection $connection, RemoteRepository $repository, ?string $file): string
    {
        if ($connection->provider === GitProvider::Github) {
            if (preg_match('/^[A-Za-z0-9_.-]+(\/[A-Za-z0-9_.-]+){1,5}$/', $repository->full_name) !== 1) {
                throw new GitRejected('نام مخزن برای خواندن نشانه‌ها مجاز نیست.');
            }
            $path = implode('/', array_map(rawurlencode(...), explode('/', $repository->full_name)));

            return 'https://api.github.com/repos/'.$path.'/contents'.($file === null ? '' : '/'.rawurlencode($file));
        }

        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $repository->provider_repository_id) !== 1) {
            throw new GitRejected('شناسه مخزن GitLab مجاز نیست.');
        }
        $base = rtrim($connection->base_url, '/').'/api/v4/projects/'.rawurlencode($repository->provider_repository_id).'/repository/';

        return $file === null ? $base.'tree' : $base.'files/'.rawurlencode($file);
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, int|string>  $query
     * @return array<int|string, mixed>|null
     */
    private function markerResponse(string $url, array $headers, array $query): ?array
    {
        try {
            $response = $this->get($url, $this->guard->pin($url), $headers, $query);
        } catch (UnsafeOutboundUrl $exception) {
            throw new GitRejected($exception->getMessage());
        }

        if ($response->status() === 404) {
            return null;
        }

        $this->assertOk($response);
        $payload = $response->json();

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param  array<int|string, mixed>|null  $payload
     */
    private function markerText(?array $payload): ?string
    {
        if ($payload === null || array_is_list($payload)) {
            return null;
        }
        $size = (int) ($payload['size'] ?? 0);
        $content = $payload['content'] ?? null;
        if ($size > 131072 || ! is_string($content) || strlen($content) > 200000) {
            return null;
        }
        $decoded = base64_decode(preg_replace('/\s+/', '', $content) ?? '', true);
        if (! is_string($decoded) || strlen($decoded) > 131072 || str_contains($decoded, "\0") || ! mb_check_encoding($decoded, 'UTF-8')) {
            return null;
        }

        return $decoded;
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

        try {
            return Http::withOptions([
                'allow_redirects' => false,
                'curl' => [
                    CURLOPT_RESOLVE => [$host.':'.$port.':'.$address],
                ],
            ])->withHeaders($headers)->timeout(8)->connectTimeout(3)->get($url, $query);
        } catch (Throwable) {
            throw new GitRejected('ارتباط با سرویس Git برقرار نشد؛ دوباره تلاش کنید.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function connectionHeaders(GitConnection $connection, string $material): array
    {
        if ($connection->provider === GitProvider::Github) {
            return $this->githubHeaders($material);
        }

        return $connection->auth_method === GitAuthMethod::Oauth
            ? ['Authorization' => 'Bearer '.$material, 'Accept' => 'application/json', 'User-Agent' => 'Rahandaz']
            : $this->gitlabHeaders($material);
    }

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
