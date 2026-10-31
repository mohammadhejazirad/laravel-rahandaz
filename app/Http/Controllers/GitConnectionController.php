<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Git\Enums\GitConnectionState;
use App\Domain\Git\Enums\GitProvider;
use App\Domain\Git\Exceptions\GitRejected;
use App\Domain\Git\GitConnector;
use App\Domain\Git\GitHubManifest;
use App\Domain\Git\GitOAuthClient;
use App\Domain\Git\GitSyncState;
use App\Http\CurrentWorkspace;
use App\Http\PaginatorPayload;
use App\Http\WorkspaceAccess;
use App\Infrastructure\Http\Exceptions\UnsafeOutboundUrl;
use App\Infrastructure\Http\PublicHttpsGuard;
use App\Infrastructure\Secrets\CredentialStore;
use App\Jobs\SyncGitRepositories;
use App\Models\AuditEvent;
use App\Models\GitConnection;
use App\Models\GitProviderSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

final class GitConnectionController extends Controller
{
    public function index(Request $request)
    {
        $workspace = CurrentWorkspace::get($request);
        $manages = WorkspaceAccess::membership($request->user(), $workspace)->role->mayDecryptSecrets();
        $connections = GitConnection::query()->forWorkspace($workspace->id)->withCount(['repositories', 'repositories as selected_count' => fn ($q) => $q->where('is_selected', true)->where('disabled', false)])->orderByDesc('id')->get()->filter(fn ($c) => Gate::allows('view', $c));

        return Inertia::render('git/index', [
            'connections' => $connections->map(fn ($c) => $this->summary($c))->values()->all(),
            'providers' => collect(['github', 'gitlab', 'gitlabSelf'])->map(function ($provider) use ($workspace) {
                $setting = GitProviderSetting::query()->where('workspace_id', $workspace->id)->where('provider', $provider)->first();

                return ['provider' => $provider, 'configured' => $setting !== null, 'client_id' => $setting?->client_id ?? '', 'app_slug' => $setting?->app_slug ?? '', 'base_url' => $setting?->base_url ?? ($provider === 'gitlabSelf' ? '' : 'https://'.$provider.'.com'), 'callback_url' => route('git.callback', $provider)];
            })->all(),
            'can_manage' => $manages,
            'events' => $manages ? $this->events($workspace->id) : null,
        ]);
    }

    public function manifestStart(Request $request, GitHubManifest $manifest, AuditRecorder $audit)
    {
        $workspace = CurrentWorkspace::get($request);
        WorkspaceAccess::assertManagesSecrets($request->user(), $workspace);
        // Never silently replace an existing app used by connected accounts.
        if (GitProviderSetting::query()->forWorkspace($workspace->id)->where('provider', 'github')->exists()) {
            return $this->start($request, 'github', $audit);
        }
        $state = bin2hex(random_bytes(32));
        $request->session()->put('git_manifest', [
            'state' => $state, 'user' => $request->user()->uuid, 'workspace' => $workspace->uuid, 'expires' => time() + 600,
        ]);
        $audit->record($workspace, $request->user(), 'git.app_registration_started', 'git_provider', null, AuditResult::Success, ['provider' => 'github']);

        return Inertia::render('git/register', [
            'action' => 'https://github.com/settings/apps/new?state='.$state,
            'manifest' => json_encode($manifest->definition(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function manifestCallback(Request $request, GitHubManifest $manifest, CredentialStore $credentials, AuditRecorder $audit)
    {
        $workspace = CurrentWorkspace::get($request);
        WorkspaceAccess::assertManagesSecrets($request->user(), $workspace);
        $pending = $request->session()->pull('git_manifest');
        $state = $request->query('state');
        $code = $request->query('code');
        if (! is_array($pending) || ! is_string($state) || ! hash_equals($pending['state'], $state)
            || $pending['expires'] < time() || $pending['workspace'] !== $workspace->uuid || $pending['user'] !== $request->user()->uuid
            || ! is_string($code) || preg_match('/^[A-Za-z0-9_-]{1,200}$/D', $code) !== 1) {
            $audit->record($workspace, $request->user(), 'git.app_registration_rejected', 'git_provider', null, AuditResult::Denied, ['provider' => 'github']);

            return redirect()->route('git.index')->with('error', 'تأیید ساخت برنامه نامعتبر یا منقضی است؛ دوباره شروع کنید.');
        }

        return Cache::lock('git-manifest:'.$workspace->uuid, 30)->block(3, function () use ($request, $workspace, $pending, $manifest, $code, $credentials, $audit) {
            if (! Cache::add('git-manifest-used:'.hash('sha256', $pending['state']), true, 660)
                || GitProviderSetting::query()->forWorkspace($workspace->id)->where('provider', 'github')->exists()) {
                return redirect()->route('git.index')->with('error', 'این درخواست قبلاً استفاده شده یا برنامهٔ اتصال تنظیم شده است.');
            }
            try {
                $data = $manifest->exchange($code);
                DB::transaction(function () use ($workspace, $request, $credentials, $data, $audit): void {
                    $credential = $credentials->store($workspace, $request->user(), 'github_oauth_client', $data['client_secret'], 'github');
                    $setting = GitProviderSetting::query()->create([
                        'workspace_id' => $workspace->id, 'provider' => 'github', 'base_url' => 'https://github.com',
                        'client_id' => $data['client_id'], 'app_slug' => $data['app_slug'], 'credential_id' => $credential->id,
                    ]);
                    $audit->record($workspace, $request->user(), 'git.app_registered', 'git_provider', $setting->uuid, AuditResult::Success, ['provider' => 'github']);
                });
            } catch (Throwable) {
                $audit->record($workspace, $request->user(), 'git.app_registration_failed', 'git_provider', null, AuditResult::Failure, ['provider' => 'github']);

                return redirect()->route('git.index')->with('error', 'ساخت برنامه تأیید نشد؛ دوباره تلاش کنید یا از تنظیمات پیشرفته استفاده کنید.');
            }

            // Continue straight to GitHub's repository permission chooser.
            return $this->start($request, 'github', $audit);
        });
    }

    public function settings(Request $request, string $provider, CredentialStore $credentials, AuditRecorder $audit)
    {
        $workspace = CurrentWorkspace::get($request);
        WorkspaceAccess::assertManagesSecrets($request->user(), $workspace);
        $data = $request->validate([
            'base_url' => [$provider === 'gitlabSelf' ? 'required' : 'nullable', 'string', 'max:300'],
            'client_id' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9_.-]+$/D'],
            'client_secret' => ['nullable', 'string', 'min:8', 'max:4096', 'regex:/^\S+$/D'],
            'app_slug' => [$provider === 'github' ? 'required' : 'nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/D'],
        ]);
        $baseUrl = $provider === 'gitlabSelf' ? rtrim(trim($data['base_url']), '/') : 'https://'.$provider.'.com';
        if ($provider === 'gitlabSelf') {
            $parts = parse_url($baseUrl);
            if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['query']) || isset($parts['fragment']) || ! empty($parts['path']) || strtolower($parts['host']) === 'gitlab.com') {
                throw ValidationException::withMessages(['base_url' => 'نشانی مستقل HTTPS بدون مسیر، پورت یا اطلاعات ورود وارد کنید.']);
            }
            $baseUrl = 'https://'.strtolower($parts['host']);
            try {
                app(PublicHttpsGuard::class)->pin($baseUrl);
            } catch (UnsafeOutboundUrl) {
                throw ValidationException::withMessages(['base_url' => 'سرور باید نام دامنه و IP عمومی معتبر داشته باشد؛ نشانی خصوصی یا داخلی مجاز نیست.']);
            }
        }
        $setting = GitProviderSetting::query()->where('workspace_id', $workspace->id)->where('provider', $provider)->first();
        if ($setting !== null && $setting->base_url !== $baseUrl && GitConnection::query()->forWorkspace($workspace->id)->where('base_url', $setting->base_url)->where('state', '!=', GitConnectionState::Revoked)->exists()) {
            throw ValidationException::withMessages(['base_url' => 'پیش از تغییر آدرس سرور، اتصال‌های فعال آن را قطع کنید.']);
        }
        if ($setting !== null && $setting->base_url !== $baseUrl && empty($data['client_secret'])) {
            throw ValidationException::withMessages(['client_secret' => 'برای سرور جدید، راز برنامهٔ همان سرور را وارد کنید.']);
        }
        if ($setting === null && empty($data['client_secret'])) {
            throw ValidationException::withMessages(['client_secret' => 'راز برنامه برای اولین ذخیره الزامی است.']);
        }
        DB::transaction(function () use ($request, $workspace, $provider, $data, $setting, $credentials, $audit, $baseUrl): void {
            $old = $setting?->credential;
            $credential = $old;
            if (! empty($data['client_secret'])) {
                if ($old !== null) {
                    $old->forceFill($credentials->material($workspace, $request->user(), $old->type, $data['client_secret'], $provider) + ['rotated_at' => now()])->save();
                } else {
                    $credential = $credentials->store($workspace, $request->user(), $provider.'_oauth_client', $data['client_secret'], $provider);
                }
            }
            $setting = GitProviderSetting::query()->updateOrCreate(['workspace_id' => $workspace->id, 'provider' => $provider], [
                'base_url' => $baseUrl, 'client_id' => $data['client_id'], 'app_slug' => $provider === 'github' ? $data['app_slug'] : null, 'credential_id' => $credential->id,
            ]);
            if ($old !== null && $old->id !== $credential->id) {
                $old->forceFill(['revoked_at' => now()])->save();
            }
            $audit->record($workspace, $request->user(), 'git.settings', 'git_provider', $setting->uuid, AuditResult::Success, ['provider' => $provider], $request->attributes->get('request_id'), $request->ip());
        });

        return back()->with('status', 'تنظیمات برنامه ذخیره شد.');
    }

    public function start(Request $request, string $provider, AuditRecorder $audit)
    {
        $workspace = CurrentWorkspace::get($request);
        WorkspaceAccess::assertManagesSecrets($request->user(), $workspace);
        $setting = GitProviderSetting::query()->where('workspace_id', $workspace->id)->where('provider', $provider)->first();
        if ($setting === null) {
            return back()->with('warning', 'مدیر باید تنظیم برنامهٔ اتصال را یک‌بار ثبت کند؛ سپس این دکمه شما را مستقیم به سرویس می‌برد.');
        }
        if ($provider === 'gitlabSelf') {
            try {
                app(PublicHttpsGuard::class)->pin($setting->base_url);
            } catch (UnsafeOutboundUrl) {
                return back()->with('error', 'آدرس سرور GitLab شخصی به نشانی عمومی مجاز ترجمه نمی‌شود.');
            }
        }
        $state = bin2hex(random_bytes(32));
        $request->session()->put('git_oauth.'.$provider, [
            'state' => $state, 'user' => $request->user()->uuid, 'workspace' => $workspace->uuid,
            'setting' => $setting->uuid, 'expires' => time() + 600, 'phase' => $provider === 'github' ? 'installation' : 'authorize',
            'verifier' => bin2hex(random_bytes(32)), 'settings_version' => $setting->authorizationVersion(),
        ]);
        $audit->record($workspace, $request->user(), 'git.authorization_started', 'git_provider', $setting->uuid, AuditResult::Success, ['provider' => $provider], $request->attributes->get('request_id'), $request->ip());
        $url = $provider === 'github'
            ? 'https://github.com/apps/'.rawurlencode($setting->app_slug).'/installations/new?state='.$state
            : app(GitOAuthClient::class)->authorizeUrl($setting, $state, $request->session()->get('git_oauth.'.$provider.'.verifier'));

        return Inertia::location($url);
    }

    public function callback(Request $request, string $provider, GitOAuthClient $oauth, GitConnector $connector, AuditRecorder $audit)
    {
        $workspace = CurrentWorkspace::get($request);
        WorkspaceAccess::assertManagesSecrets($request->user(), $workspace);
        $pending = $request->session()->pull('git_oauth.'.$provider);
        $state = $request->query('state');
        if (! is_array($pending) || ! is_string($state) || ! hash_equals($pending['state'], $state) || $pending['expires'] < time() || $pending['workspace'] !== $workspace->uuid || $pending['user'] !== $request->user()->uuid) {
            $audit->record($workspace, $request->user(), 'git.authorization_rejected', 'git_provider', null, AuditResult::Denied, ['provider' => $provider, 'reason' => 'invalid_state']);

            return redirect()->route('git.index')->with('error', 'تأیید اتصال نامعتبر یا منقضی است؛ دوباره شروع کنید.');
        }

        return Cache::lock('git-callback:'.hash('sha256', $state), 40)->block(3, function () use ($request, $provider, $pending, $oauth, $connector, $workspace, $audit) {
            // The single-use marker also covers concurrent callbacks from the same session.
            if (! Cache::add('git-used:'.hash('sha256', $pending['state']), true, 660)) {
                return redirect()->route('git.index')->with('error', 'این تأیید قبلاً استفاده شده است.');
            }
            $setting = GitProviderSetting::query()->where('workspace_id', $workspace->id)->where('uuid', $pending['setting'])->firstOrFail();
            try {
                if ($pending['settings_version'] !== $setting->authorizationVersion()) {
                    throw new GitRejected('تنظیمات برنامه تغییر کرده است؛ اتصال را دوباره آغاز کنید.');
                }
                if ($request->has('error')) {
                    throw new GitRejected('مجوز اتصال تأیید نشد.');
                }
                if ($provider === 'github' && $pending['phase'] === 'installation') {
                    $installation = $request->query('installation_id');
                    if (! is_string($installation) || preg_match('/^[0-9]{1,32}$/D', $installation) !== 1) {
                        throw new GitRejected('نصب GitHub تأیید نشد؛ درخواست تأیید سازمان ممکن است در انتظار مدیر باشد.');
                    }
                    $pending['installation_id'] = $installation;
                    $pending['phase'] = 'authorize';
                    $pending['state'] = bin2hex(random_bytes(32));
                    $request->session()->put('git_oauth.'.$provider, $pending);
                    $audit->record($workspace, $request->user(), 'git.installation_returned', 'git_provider', $setting->uuid, AuditResult::Success, ['provider' => $provider, 'verified' => false]);

                    return redirect()->away($oauth->authorizeUrl($setting, $pending['state'], $pending['verifier']));
                }
                $code = $request->query('code');
                if (! is_string($code) || $code === '' || strlen($code) > 2048) {
                    throw new GitRejected('کد تأیید معتبر دریافت نشد.');
                }
                $tokens = $oauth->exchange($setting, $request->user(), [
                    'code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => route('git.callback', $provider), 'code_verifier' => $pending['verifier'],
                ]);
                $connection = $connector->connect($workspace, $request->user(), GitProvider::from($provider === 'gitlabSelf' ? 'gitlab' : $provider), $setting->base_url, $tokens['access_token'], 'user', $request->attributes->get('request_id'), $request->ip(), $tokens, $pending['installation_id'] ?? null);
            } catch (Throwable $e) {
                $audit->record($workspace, $request->user(), 'git.authorization_failed', 'git_provider', $setting->uuid, AuditResult::Failure, ['provider' => $provider, 'reason' => 'authorization_failed']);

                return redirect()->route('git.index')->with('error', $e instanceof GitRejected ? $e->getMessage() : 'اتصال ذخیره نشد؛ تنظیمات برنامه را بررسی و دوباره تلاش کنید.');
            }

            SyncGitRepositories::dispatch($connection->uuid, $request->user()->uuid);

            return redirect()->route('git.show', $connection)->with('status', 'هویت حساب تأیید شد. اکنون مخزن‌ها را دریافت و دسترسی‌های دلخواه را انتخاب کنید.');
        });
    }

    public function testProvider(Request $request, string $provider, GitConnector $connector, AuditRecorder $audit)
    {
        $workspace = CurrentWorkspace::get($request);
        WorkspaceAccess::assertManagesSecrets($request->user(), $workspace);
        $data = $request->validate(['mode' => ['required', 'in:manual,automatic']]);
        $manual = $data['mode'] === 'manual';
        $action = $manual ? 'git.manual_test' : 'git.oauth_preflight';
        $audit->record($workspace, $request->user(), $action.'_started', 'git_provider', null, AuditResult::Success, ['provider' => $provider]);
        try {
            if ($manual) {
                [$domainProvider, $baseUrl, $material] = $this->manualInput($request, $provider);
                $connector->testAccess($domainProvider, $baseUrl, $material);
            } else {
                $setting = GitProviderSetting::query()->forWorkspace($workspace->id)->where('provider', $provider)->first();
                if ($setting === null) {
                    $audit->record($workspace, $request->user(), $action, 'git_provider', null, AuditResult::Failure, ['provider' => $provider]);

                    return back()->with('error', 'ابتدا تنظیمات برنامهٔ اتصال خودکار را ذخیره کنید.');
                }
                $connector->testAuthorizationPage($setting);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable) {
            $audit->record($workspace, $request->user(), $action, 'git_provider', null, AuditResult::Failure, ['provider' => $provider]);

            return back()->with('error', 'تست انجام نشد؛ آدرس سرویس، شبکه و مجوزهای دسترسی را بررسی کنید.');
        }
        $audit->record($workspace, $request->user(), $action, 'git_provider', null, AuditResult::Success, ['provider' => $provider]);

        return back()->with($manual ? 'status' : 'info', $manual ? 'هویت حساب و دسترسی خواندن فهرست مخزن‌ها تأیید شد؛ توکن ذخیره نشد.' : 'صفحهٔ مجوز سرویس در دسترس است. اعتبار حساب و Client Secret فقط با تکمیل ورود خودکار تأیید می‌شود.');
    }

    public function manual(Request $request, string $provider, GitConnector $connector)
    {
        $workspace = CurrentWorkspace::get($request);
        WorkspaceAccess::assertManagesSecrets($request->user(), $workspace);
        [$domainProvider, $baseUrl, $material] = $this->manualInput($request, $provider);
        try {
            $connector->testAccess($domainProvider, $baseUrl, $material);
            $connection = $connector->connect($workspace, $request->user(), $domainProvider, $baseUrl, $material, 'user', $request->attributes->get('request_id'), $request->ip());
        } catch (Throwable) {
            app(AuditRecorder::class)->record($workspace, $request->user(), 'git.manual_connect_failed', 'git_provider', null, AuditResult::Failure, ['provider' => $provider]);

            return back()->with('error', 'اتصال دستی ثبت نشد؛ توکن، مجوز خواندن مخزن‌ها و اتصال تکراری را بررسی کنید.');
        }
        SyncGitRepositories::dispatch($connection->uuid, $request->user()->uuid);

        return redirect()->route('git.show', $connection)->with('status', 'اتصال دستی پس از بررسی هویت و دسترسی ذخیره شد؛ دریافت مخزن‌ها در صف است.');
    }

    public function testConnection(Request $request, GitConnection $connection, GitConnector $connector, GitOAuthClient $oauth)
    {
        $this->authorizeConnection($request, $connection, 'update');
        abort_if($connection->state === GitConnectionState::Revoked, 409);
        $this->log($request, $connection, 'git.connection_test_started', AuditResult::Success);
        try {
            $material = $oauth->accessToken($connection, $request->user());
            $identity = $connector->testAccess($connection->provider, $connection->base_url, $material, in_array($connection->auth_method->value, ['oauth', 'github_app'], true), $connection->installation_id);
            if ($connection->auth_method->value !== 'github_app' && $identity['external_account_id'] !== $connection->external_account_id) {
                throw new GitRejected('هویت حساب با اتصال ذخیره‌شده مطابقت ندارد.');
            }
        } catch (Throwable) {
            $this->log($request, $connection, 'git.connection_test', AuditResult::Failure);

            return back()->with('error', 'تست اتصال ناموفق بود؛ مجوز سرویس، انقضای توکن یا شبکه را بررسی کنید.');
        }
        $this->log($request, $connection, 'git.connection_test', AuditResult::Success);

        return back()->with('status', 'هویت حساب و دسترسی خواندن فهرست مخزن‌ها در سرویس تأیید شد.');
    }

    private function manualInput(Request $request, string $provider): array
    {
        $data = $request->validate(['access_token' => ['required', 'string', 'min:8', 'max:255', 'regex:/^\S+$/D'], 'base_url' => [$provider === 'gitlabSelf' ? 'required' : 'nullable', 'string', 'max:300']]);
        $baseUrl = $provider === 'gitlabSelf' ? rtrim(trim($data['base_url']), '/') : 'https://'.$provider.'.com';
        if ($provider === 'gitlabSelf') {
            $parts = parse_url($baseUrl);
            if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['query']) || isset($parts['fragment']) || ! empty($parts['path']) || strtolower($parts['host']) === 'gitlab.com') {
                throw ValidationException::withMessages(['base_url' => 'نشانی مستقل HTTPS بدون مسیر یا پورت وارد کنید.']);
            }
        }

        return [GitProvider::from($provider === 'gitlabSelf' ? 'gitlab' : $provider), $baseUrl, $data['access_token']];
    }

    public function show(Request $request, GitConnection $connection)
    {
        $this->authorizeConnection($request, $connection, 'view');
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:200']]);
        $search = trim($filters['search'] ?? '');
        $repositories = $connection->repositories()->when($search !== '', fn ($q) => $q->whereRaw('strpos(lower(full_name), lower(?)) > 0', [$search]))->recentFirst()->paginate(50, ['*'], 'repositories_page')->withQueryString();

        return Inertia::render('git/show', [
            'connection' => $this->summary($connection),
            'filters' => ['search' => $search],
            'repositories' => ['data' => $repositories->getCollection()->map(fn ($r) => ['uuid' => $r->uuid, 'full_name' => $r->full_name, 'default_branch' => $r->default_branch, 'visibility' => $r->visibility->value, 'archived' => $r->archived, 'disabled' => $r->disabled, 'is_selected' => $r->is_selected])->all(), 'meta' => PaginatorPayload::meta($repositories)],
            'events' => $this->events($connection->workspace_id, $connection->uuid),
            'can_manage' => Gate::allows('update', $connection),
            'manage_url' => $connection->provider === GitProvider::Github && $connection->installation_id !== null ? ($connection->external_account_type === 'organization' ? 'https://github.com/organizations/'.rawurlencode($connection->account_label).'/settings/installations/' : 'https://github.com/settings/installations/').$connection->installation_id : $connection->base_url.'/-/user_settings/applications',
        ]);
    }

    public function sync(Request $request, GitConnection $connection, GitConnector $connector)
    {
        $this->authorizeConnection($request, $connection, 'update');

        abort_if($connection->state === GitConnectionState::Revoked, 409);
        $this->log($request, $connection, 'git.sync_requested', AuditResult::Success);
        try {
            SyncGitRepositories::dispatch($connection->uuid, $request->user()->uuid);
        } catch (Throwable $exception) {
            $this->log($request, $connection, 'git.sync_failed', AuditResult::Failure);
            throw $exception;
        }

        return back()->with('info', 'درخواست دریافت مخزن‌ها ثبت شد؛ نتیجه و مرحله‌ها در تاریخچه نمایش داده می‌شوند.');
    }

    public function select(Request $request, GitConnection $connection)
    {
        $this->authorizeConnection($request, $connection, 'update');
        $data = $request->validate([
            'visible_uuids' => ['required', 'array', 'min:1', 'max:50'], 'visible_uuids.*' => ['required', 'uuid:7', 'distinct'],
            'selected_uuids' => ['present', 'array', 'max:50'], 'selected_uuids.*' => ['required', 'uuid:7', 'distinct'],
        ]);
        DB::transaction(function () use ($request, $connection, $data): void {
            $connection = GitConnection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();
            abort_if($connection->state === GitConnectionState::Revoked, 409);
            $rows = $connection->repositories()->whereIn('uuid', $data['visible_uuids'])->lockForUpdate()->get();
            abort_unless($rows->count() === count($data['visible_uuids']) && array_diff($data['selected_uuids'], $data['visible_uuids']) === [], 422);
            foreach ($rows as $row) {
                $selected = in_array($row->uuid, $data['selected_uuids'], true);
                abort_if($selected && ($row->disabled || $row->archived), 422);
                $row->forceFill(['is_selected' => $selected])->save();
                $this->log($request, $connection, $selected ? 'git.repository_allowed' : 'git.repository_denied', AuditResult::Success, ['repository_uuid' => $row->uuid, 'repository_name' => $row->full_name]);
            }
        });

        return back()->with('status', 'دسترسی مخزن‌های این صفحه ذخیره شد.');
    }

    public function disconnect(Request $request, GitConnection $connection, GitConnector $connector)
    {
        $this->authorizeConnection($request, $connection, 'update');
        $request->validate(['confirm' => ['accepted']]);
        DB::transaction(function () use ($request, $connection, $connector): void {
            GitConnection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();
            $connector->disconnect($connection, $request->user(), $request->attributes->get('request_id'), $request->ip());
            $connection->repositories()->update(['is_selected' => false, 'disabled' => true]);
            $connection->credential->forceFill(['revoked_at' => now()])->save();
        });

        return back()->with('status', 'دسترسی این حساب در پنل قطع شد. برای حذف مجوز در سرویس، از مدیریت دسترسی سرویس استفاده کنید.');
    }

    private function authorizeConnection(Request $request, GitConnection $connection, string $ability): void
    {
        abort_unless((string) $connection->workspace_id === (string) CurrentWorkspace::get($request)->id, 404);
        Gate::authorize($ability, $connection);
    }

    private function log(Request $request, GitConnection $connection, string $action, AuditResult $result, array $metadata = []): void
    {
        app(AuditRecorder::class)->record($connection->workspace, $request->user(), $action, 'git_connection', $connection->uuid, $result, $metadata, $request->attributes->get('request_id'), $request->ip());
    }

    private function events(int|string $workspaceId, ?string $connectionUuid = null): array
    {
        $events = AuditEvent::query()->where('workspace_id', $workspaceId)->where('action', 'like', 'git.%')
            ->when($connectionUuid !== null, fn ($q) => $q->where('resource_id', $connectionUuid))
            ->orderByDesc('id')->paginate(30, ['*'], 'events_page')->withQueryString();

        $accounts = GitConnection::query()->forWorkspace($workspaceId)->get(['uuid', 'account_label', 'provider'])->keyBy('uuid');

        return ['data' => $events->getCollection()->map(fn ($e) => [
            'uuid' => $e->uuid, 'action' => $e->action, 'result' => $e->result->value,
            'recorded_at' => $e->recorded_at->utc()->toIso8601ZuluString(),
            'repository_name' => $e->metadata['repository_name'] ?? null,
            'repository_count' => $e->metadata['repository_count'] ?? null,
            'page' => $e->metadata['page'] ?? null,
            'account_label' => $e->metadata['account_label'] ?? $accounts->get($e->resource_id)?->account_label,
            'provider' => $e->metadata['provider'] ?? $accounts->get($e->resource_id)?->provider?->value,
        ])->all(), 'meta' => PaginatorPayload::meta($events)];
    }

    private function summary(GitConnection $c): array
    {
        return ['uuid' => $c->uuid, 'provider' => $c->provider === GitProvider::Gitlab && $c->base_url !== 'https://gitlab.com' ? 'gitlabSelf' : $c->provider->value, 'base_url' => $c->base_url, 'account_label' => $c->account_label, 'auth_method' => $c->auth_method->value,
            'state' => $c->state->value, 'last_synced_at' => $c->last_synced_at?->utc()->toIso8601ZuluString(), 'expires_at' => $c->expires_at?->utc()->toIso8601ZuluString(),
            'sync_pending' => app(GitSyncState::class)->pending($c),
            'repository_count' => $c->repositories_count ?? $c->repositories()->count(), 'selected_count' => $c->selected_count ?? $c->repositories()->where('is_selected', true)->where('disabled', false)->count()];
    }
}
