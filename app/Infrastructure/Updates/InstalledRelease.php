<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates;

use InvalidArgumentException;

final class InstalledRelease
{
    public function __construct(private readonly string $path) {}

    public static function fromConfig(string $installedPath = '/var/lib/rahandaz/release.json'): self
    {
        $configured = (string) config('rahandaz.release_file');
        if ($configured === '') {
            $configured = is_file($installedPath) && ! is_link($installedPath)
                ? $installedPath
                : base_path('installer/release/development.json');
        }

        return new self($configured);
    }

    /**
     * @return array{
     *     product: string,
     *     version: string,
     *     channel: string,
     *     release_id: string,
     *     git_commit: string,
     *     installed_at: string,
     *     updated_at: string,
     *     manifest_digest: string,
     *     schema_version: string,
     *     installer_version: string,
     *     updater_version: string,
     *     php: string,
     *     source: string
     * }
     */
    public function toArray(): array
    {
        if (! is_file($this->path)) {
            throw new InvalidArgumentException('The Rahandaz release record is missing.');
        }
        $decoded = json_decode((string) file_get_contents($this->path), true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('The Rahandaz release record is unreadable.');
        }
        $version = $this->stringField($decoded, 'version');
        $channel = $this->stringField($decoded, 'channel');
        if (preg_match('/^[0-9A-Za-z][0-9A-Za-z._-]{0,62}$/', $version) !== 1) {
            throw new InvalidArgumentException('The installed version is invalid.');
        }
        if (! in_array($channel, ['stable', 'beta', 'development'], true)) {
            throw new InvalidArgumentException('The release channel is invalid.');
        }

        return [
            'product' => 'Rahandaz',
            'version' => $version,
            'channel' => $channel,
            'release_id' => $this->stringField($decoded, 'release_id'),
            'git_commit' => $this->stringField($decoded, 'git_commit'),
            'installed_at' => $this->stringField($decoded, 'installed_at'),
            'updated_at' => $this->stringField($decoded, 'updated_at'),
            'manifest_digest' => $this->stringField($decoded, 'manifest_digest'),
            'schema_version' => $this->stringField($decoded, 'schema_version'),
            'installer_version' => $this->stringField($decoded, 'installer_version'),
            'updater_version' => $this->stringField($decoded, 'updater_version'),
            'php' => PhpRuntime::SERIES,
            'source' => basename($this->path),
        ];
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function stringField(array $decoded, string $key): string
    {
        $value = $decoded[$key] ?? '';
        if (! is_string($value) || str_contains($value, "\n") || str_contains($value, "\r")) {
            return '';
        }

        return $value;
    }
}
