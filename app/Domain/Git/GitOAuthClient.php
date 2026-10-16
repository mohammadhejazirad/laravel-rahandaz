<?php

declare(strict_types=1);

namespace App\Domain\Git;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Git\Enums\GitProvider;
use App\Domain\Git\Exceptions\GitRejected;
use App\Infrastructure\Http\PublicHttpsGuard;
use App\Infrastructure\Secrets\CredentialStore;
use App\Models\GitConnection;
use App\Models\GitProviderSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

final class GitOAuthClient
{
    public function __construct(private readonly CredentialStore $credentials, private readonly PublicHttpsGuard $guard, private readonly AuditRecorder $audit) {}

    /** @return array{access_token: string, refresh_token: ?string, expires_in: ?int} */
    public function exchange(GitProviderSetting $setting, User $actor, array $parameters): array
    {
        $url = $setting->provider === 'github' ? 'https://github.com/login/oauth/access_token' : $setting->base_url.'/oauth/token';
        try {
            $secret = $this->credentials->openFor($setting->credential, $actor);
            $ip = $this->guard->pin($url);
            $host = parse_url($url, PHP_URL_HOST);
            $address = str_contains($ip, ':') ? '['.$ip.']' : $ip;
            $response = Http::asForm()->acceptJson()->withOptions([
                'allow_redirects' => false,
                'curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$address}"]],
            ])->connectTimeout(3)->timeout(10)->post($url, [
                ...$parameters, 'client_id' => $setting->client_id, 'client_secret' => $secret,
            ]);
            $access = $response->json('access_token');
            if (! $response->successful() || ! is_string($access) || $access === '' || strlen($access) > 4096) {
                throw new GitRejected('تأیید اتصال پذیرفته نشد؛ دوباره اتصال را آغاز کنید.');
            }

            return [
                'access_token' => $access,
                'refresh_token' => is_string($response->json('refresh_token')) ? $response->json('refresh_token') : null,
                'expires_in' => is_numeric($response->json('expires_in')) ? (int) $response->json('expires_in') : null,
            ];
        } catch (Throwable) {
            // Never expose an HTTP exception: request bodies contain client credentials.
            throw new GitRejected('تبادل مجوز با سرویس انجام نشد؛ تنظیمات برنامه یا اتصال شبکه را بررسی کنید.');
        }
    }

    public function accessToken(GitConnection $connection, User $actor): string
    {
        $material = $this->credentials->openFor($connection->credential, $actor);
        if (! in_array($connection->auth_method->value, ['oauth', 'github_app'], true)) {
            return $material;
        }
        $bundle = json_decode($material, true, flags: JSON_THROW_ON_ERROR);
        if ($connection->expires_at === null || $connection->expires_at->isAfter(now()->addMinute())) {
            return $bundle['access_token'];
        }

        return Cache::lock('git-refresh:'.$connection->uuid, 30)->block(5, function () use ($connection, $actor): string {
            $connection->refresh()->load('credential');
            $bundle = json_decode($this->credentials->openFor($connection->credential, $actor), true, flags: JSON_THROW_ON_ERROR);
            if ($connection->expires_at?->isAfter(now()->addMinute())) {
                return $bundle['access_token'];
            }
            $setting = GitProviderSetting::query()->where('workspace_id', $connection->workspace_id)->where('provider', $connection->provider->value === 'gitlab' && $connection->base_url !== 'https://gitlab.com' ? 'gitlabSelf' : $connection->provider->value)->where('base_url', $connection->base_url)->first();
            if ($setting === null || empty($bundle['refresh_token'])) {
                throw new GitRejected('مجوز اتصال منقضی شده است؛ حساب را دوباره متصل کنید.');
            }
            $this->audit->record($connection->workspace, $actor, 'git.refresh_started', 'git_connection', $connection->uuid, AuditResult::Success);
            try {
                $tokens = $this->exchange($setting, $actor, ['grant_type' => 'refresh_token', 'refresh_token' => $bundle['refresh_token']]);
            } catch (GitRejected $e) {
                $this->audit->record($connection->workspace, $actor, 'git.refresh_failed', 'git_connection', $connection->uuid, AuditResult::Failure);
                throw $e;
            }
            $tokens['refresh_token'] ??= $bundle['refresh_token'];
            DB::transaction(function () use ($connection, $actor, $tokens): void {
                $connection->credential->forceFill($this->credentials->material($connection->workspace, $actor, $connection->credential->type, json_encode($tokens, JSON_THROW_ON_ERROR), $connection->account_label) + ['rotated_at' => now()])->save();
                $connection->forceFill(['expires_at' => $tokens['expires_in'] ? now()->addSeconds($tokens['expires_in']) : null])->save();
            });

            $this->audit->record($connection->workspace, $actor, 'git.refresh', 'git_connection', $connection->uuid, AuditResult::Success);

            return $tokens['access_token'];
        });
    }

    public function authorizeUrl(GitProviderSetting $setting, string $state, string $verifier): string
    {
        $url = $setting->provider === GitProvider::Github->value ? 'https://github.com/login/oauth/authorize' : $setting->base_url.'/oauth/authorize';
        $params = [
            'client_id' => $setting->client_id, 'redirect_uri' => route('git.callback', $setting->provider),
            'response_type' => 'code', 'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ];
        if ($setting->provider !== 'github') {
            $params['scope'] = 'read_user read_api read_repository';
        }

        return $url.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
