<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates;

use App\Support\SemanticVersion;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class GitHubReleaseClient
{
    public function __construct(private readonly int $backoffMilliseconds = 200) {}

    public function check(string $repository, string $channel, string $installedVersion, ?string $etag): ReleaseCheckResult
    {
        $repository = trim($repository);
        if ($repository === '') {
            return ReleaseCheckResult::unconfigured();
        }
        if (preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repository) !== 1) {
            return ReleaseCheckResult::failed('invalid_repository');
        }
        if (! in_array($channel, ['stable', 'beta'], true)) {
            return ReleaseCheckResult::unconfigured('development_channel');
        }

        $path = $channel === 'stable'
            ? '/repos/'.$repository.'/releases/latest'
            : '/repos/'.$repository.'/releases?per_page=20';

        $response = $this->send('https://api.github.com'.$path, $etag);
        if ($response === 'timeout') {
            return ReleaseCheckResult::failed('timeout');
        }
        if ($response === 'failed') {
            return ReleaseCheckResult::failed('github_unavailable');
        }
        if ($response->status() === 304) {
            return ReleaseCheckResult::notModified($this->etag($response) ?? $etag);
        }
        if (in_array($response->status(), [403, 429], true)) {
            $remaining = $response->header('X-RateLimit-Remaining');
            if ($response->status() === 429 || $remaining === '0') {
                return ReleaseCheckResult::rateLimited($this->etag($response));
            }

            return ReleaseCheckResult::failed('github_forbidden');
        }
        if ($response->status() === 404) {
            return new ReleaseCheckResult('ok', null, false, null, null, null, $this->etag($response), 'no_release');
        }
        if ($response->status() !== 200) {
            return ReleaseCheckResult::failed('github_status_'.$response->status());
        }

        $payload = $response->json();
        if ($channel === 'stable') {
            return $this->fromRelease(is_array($payload) ? $payload : null, $installedVersion, $this->etag($response), false);
        }
        if (! is_array($payload)) {
            return ReleaseCheckResult::failed('malformed');
        }
        $selected = null;
        foreach ($payload as $release) {
            if (! is_array($release) || ($release['draft'] ?? false) === true) {
                continue;
            }
            $candidate = $this->fromRelease($release, $installedVersion, $this->etag($response), true);
            if ($candidate->errorCode === 'malformed') {
                return $candidate;
            }
            if ($candidate->latestVersion === null) {
                continue;
            }
            if ($selected === null || SemanticVersion::compare((string) $candidate->latestVersion, (string) $selected->latestVersion) === 1) {
                $selected = $candidate;
            }
        }

        return $selected ?? new ReleaseCheckResult('ok', null, false, null, null, null, $this->etag($response), 'no_release');
    }

    private function send(string $url, ?string $etag): Response|string
    {
        $attempt = 0;
        $sleep = $this->backoffMilliseconds;
        while ($attempt < 3) {
            $attempt++;
            try {
                $headers = [
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'Rahandaz',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ];
                if ($etag !== null && $etag !== '') {
                    $headers['If-None-Match'] = $etag;
                }
                $response = Http::timeout(5)
                    ->connectTimeout(3)
                    ->withHeaders($headers)
                    ->get($url);
            } catch (ConnectionException) {
                if ($attempt === 3) {
                    return 'timeout';
                }
                $this->pause($sleep);
                $sleep *= 2;

                continue;
            }
            if ($response->serverError()) {
                if ($attempt === 3) {
                    return 'failed';
                }
                $this->pause($sleep);
                $sleep *= 2;

                continue;
            }

            return $response;
        }

        return 'failed';
    }

    /**
     * @param  array<string, mixed>|null  $release
     */
    private function fromRelease(?array $release, string $installedVersion, ?string $etag, bool $allowPrerelease): ReleaseCheckResult
    {
        if ($release === null || ! is_string($release['tag_name'] ?? null)) {
            return ReleaseCheckResult::failed('malformed');
        }
        if (($release['draft'] ?? false) === true) {
            return new ReleaseCheckResult('ok', null, false, null, null, null, $etag, 'draft');
        }
        if (! $allowPrerelease && ($release['prerelease'] ?? false) === true) {
            return new ReleaseCheckResult('ok', null, false, null, null, null, $etag, 'prerelease');
        }
        try {
            $latest = ltrim($release['tag_name'], 'v');
            $compared = SemanticVersion::compare($latest, ltrim($installedVersion, 'v'));
        } catch (InvalidArgumentException) {
            return ReleaseCheckResult::failed('malformed');
        }
        $page = is_string($release['html_url'] ?? null) ? $release['html_url'] : null;
        $page = $this->httpsUrl($page);

        return new ReleaseCheckResult(
            'ok',
            $latest,
            $compared === 1,
            is_string($release['published_at'] ?? null) ? $release['published_at'] : null,
            $page,
            $page,
            $etag,
            null,
        );
    }

    private function httpsUrl(?string $url): ?string
    {
        if ($url === null || strlen($url) > 255 || preg_match('#^https://[A-Za-z0-9._:/-]+$#', $url) !== 1) {
            return null;
        }

        return $url;
    }

    private function etag(Response $response): ?string
    {
        $etag = $response->header('ETag');
        if (! is_string($etag) || $etag === '' || strlen($etag) > 255) {
            return null;
        }

        return $etag;
    }

    private function pause(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
