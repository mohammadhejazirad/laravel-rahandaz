<?php

declare(strict_types=1);

namespace App\Domain\Projects;

use App\Domain\Projects\Data\ArchiveInspection;
use App\Domain\Projects\Exceptions\ArchiveRejected;
use ZipArchive;

final class ArchiveInspector
{
    public function inspect(string $path): ArchiveInspection
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new ArchiveRejected('فایل بارگذاری‌شده قابل خواندن نیست.');
        }

        $sizeBytes = filesize($path);
        $maxUploadBytes = (int) config('rahandaz.project_archives.max_upload_bytes');

        if (! is_int($sizeBytes) || $sizeBytes < 1 || $sizeBytes > $maxUploadBytes) {
            throw new ArchiveRejected('حجم فایل ZIP خارج از محدوده مجاز است.');
        }

        $zip = new ZipArchive;
        $result = $zip->open($path, ZipArchive::CHECKCONS);

        if ($result !== true) {
            throw new ArchiveRejected('فایل انتخاب‌شده یک ZIP سالم و قابل بررسی نیست.');
        }

        try {
            return $this->inspectOpenArchive($zip, $path, $sizeBytes);
        } finally {
            $zip->close();
        }
    }

    private function inspectOpenArchive(ZipArchive $zip, string $path, int $sizeBytes): ArchiveInspection
    {
        $entryCount = $zip->numFiles;
        $maxEntries = (int) config('rahandaz.project_archives.max_entries');

        if ($entryCount < 1) {
            throw new ArchiveRejected('فایل ZIP خالی است.');
        }

        if ($entryCount > $maxEntries) {
            throw new ArchiveRejected('تعداد فایل‌های داخل ZIP بیشتر از حد مجاز است.');
        }

        $seenPaths = [];
        $regularFiles = 0;
        $totalCompressed = 0;
        $totalUncompressed = 0;
        $maxEntryBytes = (int) config('rahandaz.project_archives.max_entry_bytes');
        $maxUncompressedBytes = (int) config('rahandaz.project_archives.max_uncompressed_bytes');
        $maxCompressionRatio = (float) config('rahandaz.project_archives.max_compression_ratio');

        for ($index = 0; $index < $entryCount; $index++) {
            $stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);

            if (! is_array($stat) || ! isset($stat['name'], $stat['size'], $stat['comp_size'])) {
                throw new ArchiveRejected('ساختار داخلی ZIP قابل اعتماد نیست.');
            }

            $name = (string) $stat['name'];
            $this->assertSafePath($name, $seenPaths);
            $this->assertRegularEntry($zip, $index);

            $entryBytes = (int) $stat['size'];
            $compressedBytes = (int) $stat['comp_size'];

            if ($entryBytes < 0 || $compressedBytes < 0 || $entryBytes > $maxEntryBytes) {
                throw new ArchiveRejected('یکی از فایل‌های داخل ZIP بیش از حد بزرگ است.');
            }

            if (($stat['encryption_method'] ?? ZipArchive::EM_NONE) !== ZipArchive::EM_NONE) {
                throw new ArchiveRejected('فایل ZIP رمزگذاری‌شده قابل استقرار نیست.');
            }

            $isDirectory = str_ends_with($name, '/');

            if (! $isDirectory) {
                $regularFiles++;
            }

            $totalCompressed += $compressedBytes;
            $totalUncompressed += $entryBytes;

            if ($totalUncompressed > $maxUncompressedBytes) {
                throw new ArchiveRejected('حجم بازشده ZIP بیشتر از حد مجاز است.');
            }

            if ($entryBytes > 1048576 && $entryBytes / max(1, $compressedBytes) > $maxCompressionRatio) {
                throw new ArchiveRejected('نسبت فشرده‌سازی ZIP غیرعادی است و احتمال بمب فشرده‌سازی وجود دارد.');
            }
        }

        if ($regularFiles < 1 || $totalUncompressed < 1) {
            throw new ArchiveRejected('فایل ZIP باید حداقل یک فایل غیرخالی داشته باشد.');
        }

        if ($totalUncompressed / max(1, $totalCompressed) > $maxCompressionRatio) {
            throw new ArchiveRejected('نسبت فشرده‌سازی کل ZIP غیرعادی است.');
        }

        $sha256 = hash_file('sha256', $path);

        if (! is_string($sha256)) {
            throw new ArchiveRejected('محاسبه اثرانگشت فایل ZIP ممکن نشد.');
        }

        return new ArchiveInspection($sha256, $sizeBytes, $entryCount, $totalUncompressed);
    }

    /**
     * @param  array<string, true>  $seenPaths
     */
    private function assertSafePath(string $name, array &$seenPaths): void
    {
        if ($name === '' || strlen($name) > 1024 || str_contains($name, "\0") || str_contains($name, '\\')) {
            throw new ArchiveRejected('یکی از مسیرهای داخل ZIP نامعتبر است.');
        }

        if (str_starts_with($name, '/') || str_starts_with($name, '//') || preg_match('/^[A-Za-z]:/', $name) === 1) {
            throw new ArchiveRejected('مسیر مطلق داخل ZIP مجاز نیست.');
        }

        $normalized = rtrim($name, '/');
        $segments = explode('/', $normalized);

        if ($normalized === '' || count($segments) > 64) {
            throw new ArchiveRejected('عمق مسیرهای داخل ZIP بیشتر از حد مجاز است.');
        }

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || strlen($segment) > 255) {
                throw new ArchiveRejected('ZIP شامل مسیر ناامن یا قابل خروج از پوشه مقصد است.');
            }
        }

        if (isset($seenPaths[$normalized])) {
            throw new ArchiveRejected('ZIP شامل مسیر تکراری است.');
        }

        $seenPaths[$normalized] = true;
    }

    private function assertRegularEntry(ZipArchive $zip, int $index): void
    {
        $attributes = 0;
        $operatingSystem = 0;

        if (! $zip->getExternalAttributesIndex($index, $operatingSystem, $attributes, ZipArchive::FL_UNCHANGED)) {
            return;
        }

        $unixType = ($attributes >> 16) & 0xF000;
        $isUnixSymlink = $unixType === 0xA000;
        $isUnsupportedUnixType = $unixType !== 0 && ! in_array($unixType, [0x4000, 0x8000], true);
        $isWindowsReparsePoint = $operatingSystem === ZipArchive::OPSYS_DOS && ($attributes & 0x0400) !== 0;

        if ($isUnixSymlink || $isUnsupportedUnixType || $isWindowsReparsePoint) {
            throw new ArchiveRejected('لینک نمادین یا ورودی ویژه داخل ZIP مجاز نیست.');
        }
    }
}
