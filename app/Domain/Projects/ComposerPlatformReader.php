<?php

declare(strict_types=1);

namespace App\Domain\Projects;

use App\Domain\Projects\Enums\ProjectSourceArchiveStatus;
use App\Models\ProjectSourceArchive;
use Throwable;
use ZipArchive;

final class ComposerPlatformReader
{
    /**
     * @return array{found: bool, php: ?string, extensions: list<string>}
     */
    public function fromArchive(ProjectSourceArchive $archive): array
    {
        $empty = ['found' => false, 'php' => null, 'extensions' => []];

        if ($archive->status !== ProjectSourceArchiveStatus::Ready || $archive->disk !== 'project_archives') {
            return $empty;
        }

        $root = storage_path('app/project-archives');
        $path = $root.'/'.ltrim($archive->path, '/');
        $realRoot = realpath($root);
        $realFile = realpath($path);

        if ($realRoot === false || $realFile === false || ! str_starts_with($realFile, $realRoot.DIRECTORY_SEPARATOR)) {
            return $empty;
        }

        $zip = new ZipArchive;
        $opened = $zip->open($realFile);

        if ($opened !== true) {
            return $empty;
        }

        try {
            $contents = $zip->getFromName('composer.json');
        } catch (Throwable) {
            $zip->close();

            return $empty;
        }

        $zip->close();

        if (! is_string($contents) || $contents === '' || strlen($contents) > 1_000_000) {
            return $empty;
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return $empty;
        }

        $extensions = [];

        foreach (['require', 'require-dev'] as $section) {
            $requirements = $decoded[$section] ?? null;

            if (! is_array($requirements)) {
                continue;
            }

            foreach ($requirements as $package => $constraint) {
                if (! is_string($package) || ! str_starts_with($package, 'ext-')) {
                    continue;
                }

                $name = substr($package, 4);

                if (preg_match('/^[a-z0-9_]{1,40}$/', $name) === 1) {
                    $extensions[] = $name;
                }
            }
        }

        $php = null;
        $phpConstraint = $decoded['require']['php'] ?? null;

        if (is_string($phpConstraint) && preg_match('/^[0-9A-Za-z.|^~<>=* -]{1,40}$/', $phpConstraint) === 1) {
            $php = $phpConstraint;
        }

        $extensions = array_values(array_unique($extensions));
        sort($extensions);

        return [
            'found' => true,
            'php' => $php,
            'extensions' => $extensions,
        ];
    }
}
