<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates;

final class UpdateStatusPresenter
{
    /**
     * @param  array<string, string>  $installed
     * @param  array<string, mixed>|null  $latest
     * @param  array<string, mixed>|null  $previousGood
     * @param  list<array<string, mixed>>  $history
     * @return array<string, mixed>
     */
    public function present(array $installed, ?array $latest, ?array $previousGood, array $history = []): array
    {
        $installedVersion = (string) ($installed['version'] ?? '');
        $channel = (string) ($installed['update_channel'] ?? 'stable');
        if (! in_array($channel, ['stable', 'beta', 'development'], true)) {
            $channel = 'stable';
        }
        $shown = $latest;
        $fresh = true;
        $state = 'not_checked';
        if ($latest === null) {
            $fresh = false;
            $state = 'not_checked';
        } else {
            $result = (string) ($latest['result'] ?? 'failed');
            if (in_array($result, ['failed', 'rate_limited'], true) && $previousGood !== null) {
                $shown = $previousGood;
                $fresh = false;
                $state = 'offline_cached';
            } elseif ($result === 'failed' || $result === 'rate_limited') {
                $fresh = false;
                $state = 'check_failed';
            } elseif ($result === 'source_unconfigured') {
                $state = 'source_unconfigured';
            } elseif ($result === 'cached') {
                $fresh = false;
                $state = 'checking';
            } elseif (($latest['error_code'] ?? null) === 'UPDATE_UNSUPPORTED') {
                $state = 'unsupported';
            } elseif (($shown['update_available'] ?? false) === true) {
                $state = 'update_available';
            } else {
                $state = 'up_to_date';
            }
        }

        $latestVersion = is_string($shown['latest_version'] ?? null) ? $shown['latest_version'] : null;
        $updateAvailable = $state === 'update_available' && $fresh;
        $cachedUpdate = $state === 'offline_cached' && ($shown['update_available'] ?? false) === true;
        $command = ($updateAvailable || $cachedUpdate) && $this->versionToken($latestVersion)
            ? 'sudo rahandaz update --version='.$latestVersion.' --manifest MANIFEST --signature SIGNATURE --public-key KEY --artifact ARTIFACT --metadata METADATA --metadata-signature METADATA_SIGNATURE'
            : 'rahandaz update check';

        return [
            'state' => $state,
            'installed_version' => $installedVersion,
            'latest_version' => $latestVersion,
            'channel' => $channel,
            'published_at' => $shown['published_at'] ?? null,
            'notes_url' => $shown['notes_url'] ?? null,
            'release_url' => $shown['release_url'] ?? null,
            'update_available' => $updateAvailable,
            'checked_at' => $latest['checked_at'] ?? null,
            'verified_at' => $fresh ? ($latest['checked_at'] ?? null) : ($previousGood['checked_at'] ?? null),
            'result' => $latest['result'] ?? null,
            'error_code' => $latest['error_code'] ?? null,
            'fresh' => $fresh,
            'command' => $command,
            'history_command' => 'rahandaz update history',
            'version_command' => 'rahandaz version',
            'php' => PhpRuntime::SERIES,
            'history' => $history,
            'panel_executes_update' => false,
        ];
    }

    private function versionToken(?string $version): bool
    {
        return is_string($version) && preg_match('/^[0-9A-Za-z][0-9A-Za-z._-]{0,62}$/', $version) === 1;
    }
}
