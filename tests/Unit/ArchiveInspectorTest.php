<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Projects\ArchiveInspector;
use App\Domain\Projects\Exceptions\ArchiveRejected;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class ArchiveInspectorTest extends TestCase
{
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_valid_archive_is_inspected_without_extraction(): void
    {
        $path = $this->zip([
            'composer.json' => '{"name":"example/project"}',
            'app/Http/Kernel.php' => '<?php final class Kernel {}',
        ]);

        $inspection = app(ArchiveInspector::class)->inspect($path);

        $this->assertSame(hash_file('sha256', $path), $inspection->sha256);
        $this->assertSame(2, $inspection->entryCount);
        $this->assertGreaterThan(0, $inspection->sizeBytes);
        $this->assertGreaterThan(0, $inspection->uncompressedBytes);
        $this->assertFileDoesNotExist(dirname($path).'/composer.json');
    }

    #[DataProvider('unsafePathProvider')]
    public function test_unsafe_archive_paths_are_rejected(string $name): void
    {
        $path = $this->zip([$name => 'unsafe']);

        $this->expectException(ArchiveRejected::class);
        app(ArchiveInspector::class)->inspect($path);
    }

    public static function unsafePathProvider(): array
    {
        return [
            'zip slip' => ['../outside.php'],
            'absolute unix path' => ['/etc/passwd'],
            'windows drive path' => ['C:/Windows/system.ini'],
            'backslash traversal' => ['..\\outside.php'],
            'duplicate separator' => ['app//config.php'],
        ];
    }

    public function test_symbolic_links_are_rejected(): void
    {
        $path = $this->zip(['target.txt' => 'target']);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $zip->addFromString('link', 'target.txt');
        $zip->setExternalAttributesName('link', ZipArchive::OPSYS_UNIX, 0120777 << 16);
        $zip->close();

        $this->expectException(ArchiveRejected::class);
        app(ArchiveInspector::class)->inspect($path);
    }

    public function test_encrypted_entries_are_rejected(): void
    {
        $path = $this->zip(['secret.txt' => 'encrypted']);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertTrue($zip->setEncryptionName('secret.txt', ZipArchive::EM_AES_256, 'password'));
        $zip->close();

        $this->expectException(ArchiveRejected::class);
        app(ArchiveInspector::class)->inspect($path);
    }

    public function test_entry_count_expanded_size_and_compression_bombs_are_limited(): void
    {
        config([
            'rahandaz.project_archives.max_entries' => 1,
            'rahandaz.project_archives.max_uncompressed_bytes' => 1024,
        ]);
        $tooMany = $this->zip(['one.txt' => 'one', 'two.txt' => 'two']);

        try {
            app(ArchiveInspector::class)->inspect($tooMany);
            $this->fail('An archive with too many entries was accepted.');
        } catch (ArchiveRejected) {
            $this->assertTrue(true);
        }

        config([
            'rahandaz.project_archives.max_entries' => 10,
            'rahandaz.project_archives.max_uncompressed_bytes' => 4096,
            'rahandaz.project_archives.max_compression_ratio' => 20,
        ]);
        $bomb = $this->zip(['large.txt' => str_repeat('A', 2 * 1024 * 1024)]);

        $this->expectException(ArchiveRejected::class);
        app(ArchiveInspector::class)->inspect($bomb);
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function zip(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rahandaz-zip-');

        if ($path === false) {
            $this->fail('Could not create a temporary ZIP path.');
        }

        $this->temporaryFiles[] = $path;
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));

        foreach ($entries as $name => $contents) {
            $this->assertTrue($zip->addFromString($name, $contents));
        }

        $this->assertTrue($zip->close());

        return $path;
    }
}
