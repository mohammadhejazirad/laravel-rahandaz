<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

/** Read-only observation: never executes binaries or treats presence as a healthcheck. */
class SoftwareInventory
{
    public function observe(array $catalog, array $verified = []): array
    {
        $status = $this->read('/var/lib/dpkg/status');
        $packages = $status === null ? null : $this->parseDpkg($status);
        $rows = [];
        foreach ($catalog as $key => $entry) {
            $state = 'unknown';
            $version = null;
            if (($entry['kind'] ?? '') === 'apt' && $packages !== null) {
                $version = $packages[$entry['apt']] ?? null;
                $state = $version === null ? 'not_installed' : ($this->exists($entry['executable_path']) ? 'installed' : 'unknown');
            } elseif (($entry['kind'] ?? '') === 'archive') {
                $marker = $this->read($entry['destination'].'/.rahandaz-install.json');
                $receipt = $marker === null ? null : json_decode($marker, true);
                if (is_array($receipt) && ($receipt['schema_version'] ?? null) === '1' && ($receipt['package'] ?? null) === $key
                    && ($receipt['version'] ?? null) === $entry['version']
                    && in_array($receipt['sha256'] ?? null, array_filter([$entry['sha256'] ?? null, ...array_column($entry['builds'] ?? [], 'sha256')]), true)
                    && $this->exists($entry['executable_path'])) {
                    $state = 'installed';
                    $version = $entry['version'];
                }
            } elseif (($entry['kind'] ?? '') === 'chrome') {
                foreach ($verified as $row) {
                    if (($row['package'] ?? null) === $key && ($row['verified'] ?? false) === true
                        && preg_match('/^[0-9]+(?:\.[0-9]+){3}$/D', (string) ($row['version'] ?? '')) === 1
                        && ($row['executable_path'] ?? null) === '/opt/rahandaz/browsers/chrome/'.$row['version'].'/chrome-linux64/chrome'
                        && $this->exists($row['executable_path'])) {
                        $state = 'installed';
                        $version = $row['version'];
                        break;
                    }
                }
            }
            $rows[$key] = ['state' => $state, 'version' => $version];
        }

        return ['checked_at' => now()->toIso8601String(), 'packages' => $rows];
    }

    public function parseDpkg(string $status): array
    {
        $packages = [];
        foreach (preg_split('/\n\s*\n/', $status) as $paragraph) {
            if (preg_match('/^Status: (?:install|hold|deinstall|purge|unknown) ok installed$/m', $paragraph) !== 1
                || preg_match('/^Package: ([a-z0-9][a-z0-9+.-]*)$/m', $paragraph, $name) !== 1
                || preg_match('/^Version: ([A-Za-z0-9.+:~_-]{1,120})$/m', $paragraph, $version) !== 1) {
                continue;
            }
            $packages[$name[1]] = $version[1];
        }

        return $packages;
    }

    protected function read(string $path): ?string
    {
        // A package transaction may replace a receipt between stat and read.
        return is_readable($path) && is_file($path) ? (@file_get_contents($path) ?: null) : null;
    }

    protected function exists(string $path): bool
    {
        return is_file($path) && is_executable($path);
    }
}
