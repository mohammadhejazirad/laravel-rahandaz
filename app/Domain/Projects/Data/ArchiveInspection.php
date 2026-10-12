<?php

declare(strict_types=1);

namespace App\Domain\Projects\Data;

final readonly class ArchiveInspection
{
    public function __construct(
        public string $sha256,
        public int $sizeBytes,
        public int $entryCount,
        public int $uncompressedBytes,
    ) {}
}
