<?php

declare(strict_types=1);

namespace App\Domain\Git;

use App\Domain\Git\Exceptions\GitRejected;
use App\Infrastructure\Http\PublicHttpsGuard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class GitHubManifest
{
    /** Only public registration data: credentials are returned directly to the backend. */
    public function definition(): array
    {
        return [
            'name' => 'Rahandaz-'.strtolower(substr(str_replace('-', '', (string) Str::uuid()), 0, 10)),
            'url' => url('/'),
            'redirect_url' => route('git.manifest.callback'),
            'callback_urls' => [route('git.callback', 'github')],
            'setup_url' => route('git.callback', 'github'),
            'setup_on_update' => true,
            'public' => false,
            'request_oauth_on_install' => false,
            // Webhooks are omitted. GitHub rejects a hook URL that is not reachable on the public
            // internet, including localhost, even when the hook is inactive. Account, repository
            // and branch selection use the installation API and do not need a hook.
            'default_permissions' => ['contents' => 'read', 'metadata' => 'read'],
            'default_events' => [],
        ];
    }

    public function exchange(string $code): array
    {
        try {
            $url = 'https://api.github.com/app-manifests/'.rawurlencode($code).'/conversions';
            $ip = app(PublicHttpsGuard::class)->pin($url);
            $address = str_contains($ip, ':') ? '['.$ip.']' : $ip;
            // GitHub rejects a JSON array. Laravel's post() default is [], which the
            // conversion endpoint answers with HTTP 422 before it accepts the code.
            $response = Http::acceptJson()->withOptions([
                'allow_redirects' => false,
                'curl' => [CURLOPT_RESOLVE => ["api.github.com:443:{$address}"]],
            ])->connectTimeout(3)->timeout(10)->withBody('{}', 'application/json')->send('POST', $url);
            $data = $response->json();
            if (! $response->successful() || ! is_array($data)
                || ! is_string($data['client_id'] ?? null) || preg_match('/^[A-Za-z0-9_.-]{1,200}$/D', $data['client_id']) !== 1
                || ! is_string($data['client_secret'] ?? null) || strlen($data['client_secret']) < 8 || strlen($data['client_secret']) > 4096
                || ! is_string($data['slug'] ?? null) || preg_match('/^[a-z0-9-]{1,100}$/D', $data['slug']) !== 1) {
                throw new GitRejected('invalid_manifest');
            }

            // The unused private key and webhook secret never leave this method.
            return ['client_id' => $data['client_id'], 'client_secret' => $data['client_secret'], 'app_slug' => $data['slug']];
        } catch (Throwable) {
            // HTTP exceptions may contain the single-use registration code or response secrets.
            throw new GitRejected('ساخت برنامه تأیید نشد؛ دوباره تلاش کنید. اگر برنامه در GitHub ساخته شده، از تنظیمات پیشرفته آن را وارد کنید.');
        }
    }
}
