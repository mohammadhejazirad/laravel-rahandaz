<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates;

use App\Models\ReleaseCheck;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class UpdateChecker
{
    public function __construct(
        private readonly GitHubReleaseClient $client,
        private readonly UpdateNotificationService $notifications,
    ) {}

    public function run(): ReleaseCheckResult
    {
        if (! Cache::add('rahandaz.update-check', '1', 55)) {
            return ReleaseCheckResult::busy();
        }
        try {
            $installed = InstalledRelease::fromConfig()->toArray();
            $channel = (string) config('rahandaz.update_channel', 'stable');
            $repository = trim((string) config('rahandaz.github_repository', ''));
            $result = $this->client->check($repository, $channel, $installed['version'], $this->previousEtag());
            $this->store($installed, $channel, $result);
            if ($result->result === 'ok' && $result->updateAvailable && $result->latestVersion !== null) {
                $this->notifications->notifyOwners($result->latestVersion, $installed['version']);
            }

            return $result;
        } finally {
            Cache::forget('rahandaz.update-check');
        }
    }

    /**
     * @param  array<string, string>  $installed
     */
    private function store(array $installed, string $channel, ReleaseCheckResult $result): void
    {
        $latestVersion = $result->latestVersion;
        $updateAvailable = $result->updateAvailable;
        $publishedAt = $result->publishedAt;
        $notesUrl = $result->notesUrl;
        $releaseUrl = $result->releaseUrl;
        if ($result->result === 'not_modified') {
            $previous = $this->previousGood();
            if ($previous !== null) {
                $latestVersion = $previous->latest_version;
                $updateAvailable = (bool) $previous->update_available;
                $publishedAt = $previous->published_at?->toIso8601String();
                $notesUrl = $previous->notes_url;
                $releaseUrl = $previous->release_url;
            }
        }
        if (! in_array($channel, ['stable', 'beta', 'development'], true)) {
            $channel = 'stable';
        }
        try {
            if (! Schema::hasTable('release_checks')) {
                return;
            }
            ReleaseCheck::query()->create([
                'channel' => $channel,
                'installed_version' => $installed['version'],
                'latest_version' => $latestVersion,
                'update_available' => $updateAvailable,
                'published_at' => $publishedAt,
                'notes_url' => $notesUrl,
                'release_url' => $releaseUrl,
                'result' => $result->result,
                'etag' => $result->etag,
                'checked_at' => now(),
                'error_code' => $result->errorCode,
            ]);
        } catch (Throwable) {
            return;
        }
    }

    private function previousEtag(): ?string
    {
        $previous = $this->previousGood();
        $etag = $previous?->etag;

        return is_string($etag) && $etag !== '' ? $etag : null;
    }

    private function previousGood(): ?ReleaseCheck
    {
        try {
            if (! Schema::hasTable('release_checks')) {
                return null;
            }

            return ReleaseCheck::query()
                ->whereIn('result', ['ok', 'not_modified'])
                ->orderByDesc('id')
                ->first();
        } catch (Throwable) {
            return null;
        }
    }
}
