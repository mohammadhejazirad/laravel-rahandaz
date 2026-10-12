<?php

declare(strict_types=1);

namespace App\Infrastructure\Secrets;

use App\Infrastructure\Secrets\Exceptions\MasterKeyUnavailable;

final class MasterKey
{
    public function __construct(private readonly string $path) {}

    public static function fromConfig(): self
    {
        return new self((string) config('rahandaz.master_key_file'));
    }

    public function path(): string
    {
        return $this->path;
    }

    public function bytes(): string
    {
        if ($this->path === '' || ! is_file($this->path)) {
            throw new MasterKeyUnavailable('Master key file is missing.');
        }

        $encoded = trim((string) file_get_contents($this->path));
        $key = base64_decode($encoded, true);

        if ($key === false || strlen($key) !== 32) {
            throw new MasterKeyUnavailable('Master key file is invalid.');
        }

        return $key;
    }
}
