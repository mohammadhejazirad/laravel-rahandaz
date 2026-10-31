<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Catalog\SoftwareCapability;
use App\Domain\Catalog\SoftwareInventory;
use Tests\TestCase;

class SoftwareInventoryTest extends TestCase
{
    private function inventory(array $files, array $executables = []): SoftwareInventory
    {
        return new class($files, $executables) extends SoftwareInventory
        {
            public function __construct(private array $files, private array $executables) {}

            protected function read(string $path): ?string
            {
                return $this->files[$path] ?? null;
            }

            protected function exists(string $path): bool
            {
                return in_array($path, $this->executables, true);
            }
        };
    }

    public function test_dpkg_observes_external_installations_and_excludes_removed_or_invalid_versions(): void
    {
        $status = "Package: php8.4-fpm\nStatus: install ok installed\nVersion: 8.4.27-1ubuntu1\n\nPackage: jq\nStatus: deinstall ok config-files\nVersion: 1.7\n\nPackage: htop\nStatus: install ok installed\nVersion: bad version\n";
        $inventory = $this->inventory(['/var/lib/dpkg/status' => $status], ['/usr/bin/php8.4']);
        $rows = $inventory->observe(app(SoftwareCapability::class)->catalog())['packages'];
        $this->assertSame(['state' => 'installed', 'version' => '8.4.27-1ubuntu1'], $rows['php']);
        $this->assertSame('not_installed', $rows['jq']['state']);
        $this->assertArrayNotHasKey('htop', $inventory->parseDpkg($status));
        $this->assertSame('unknown', $rows['google-chrome']['state']);
        $this->assertSame(['jq' => '1.7'], $inventory->parseDpkg("Package: jq\nStatus: hold ok installed\nVersion: 1.7\n"));
    }

    public function test_unreadable_inventory_or_missing_executable_never_claims_installation(): void
    {
        $catalog = app(SoftwareCapability::class)->catalog();
        $this->assertSame('unknown', $this->inventory([])->observe($catalog)['packages']['php']['state']);
        $status = "Package: php8.4-fpm\nStatus: install ok installed\nVersion: 8.4.27\n";
        $this->assertSame('unknown', $this->inventory(['/var/lib/dpkg/status' => $status])->observe($catalog)['packages']['php']['state']);
    }

    public function test_archive_requires_matching_receipt_checksum_and_current_binary(): void
    {
        $catalog = app(SoftwareCapability::class)->catalog();
        foreach (['node', 'mongodb'] as $key) {
            $entry = $catalog[$key];
            $sha = $entry['sha256'] ?? $entry['builds']['24.04']['sha256'];
            $path = $entry['destination'].'/.rahandaz-install.json';
            $receipt = ['schema_version' => '1', 'package' => $key, 'version' => $entry['version'], 'sha256' => $sha];
            $files = [$path => json_encode($receipt)];
            $this->assertSame('installed', $this->inventory($files, [$entry['executable_path']])->observe($catalog)['packages'][$key]['state']);
            $this->assertSame('unknown', $this->inventory($files)->observe($catalog)['packages'][$key]['state']);
            $receipt['sha256'] = str_repeat('0', 64);
            $this->assertSame('unknown', $this->inventory([$path => json_encode($receipt)], [$entry['executable_path']])->observe($catalog)['packages'][$key]['state']);
        }
    }

    public function test_chrome_path_requires_verified_version_bound_evidence_and_current_binary(): void
    {
        $capability = app(SoftwareCapability::class);
        $catalog = $capability->catalog();
        $path = '/opt/rahandaz/browsers/chrome/156.0.8078.73/chrome-linux64/chrome';
        $row = ['package' => 'google-chrome', 'version' => '156.0.8078.73', 'verified' => true, 'executable_path' => $path];
        $this->assertSame($path, $capability->verifiedExecutablePath($catalog['google-chrome'], $row));
        $this->assertSame('installed', $this->inventory([], [$path])->observe($catalog, [$row])['packages']['google-chrome']['state']);
        $this->assertSame('unknown', $this->inventory([])->observe($catalog, [$row])['packages']['google-chrome']['state']);
        foreach (['/tmp/chrome', '/opt/rahandaz/browsers/chrome/1.2.3.4/chrome-linux64/chrome'] as $invalid) {
            $row['executable_path'] = $invalid;
            $this->assertNull($capability->verifiedExecutablePath($catalog['google-chrome'], $row));
            $this->assertSame('unknown', $this->inventory([], [$invalid])->observe($catalog, [$row])['packages']['google-chrome']['state']);
        }
    }
}
