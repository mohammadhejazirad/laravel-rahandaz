<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates;

final class ReleaseCheckResult
{
    public function __construct(
        public readonly string $result,
        public readonly ?string $latestVersion,
        public readonly bool $updateAvailable,
        public readonly ?string $publishedAt,
        public readonly ?string $notesUrl,
        public readonly ?string $releaseUrl,
        public readonly ?string $etag,
        public readonly ?string $errorCode,
    ) {}

    public static function unconfigured(string $errorCode = 'source_unconfigured'): self
    {
        return new self('source_unconfigured', null, false, null, null, null, null, $errorCode);
    }

    public static function failed(string $errorCode): self
    {
        return new self('failed', null, false, null, null, null, null, $errorCode);
    }

    public static function rateLimited(?string $etag): self
    {
        return new self('rate_limited', null, false, null, null, null, $etag, 'rate_limited');
    }

    public static function notModified(?string $etag): self
    {
        return new self('not_modified', null, false, null, null, null, $etag, null);
    }

    public static function busy(): self
    {
        return new self('cached', null, false, null, null, null, null, 'check_in_progress');
    }
}
