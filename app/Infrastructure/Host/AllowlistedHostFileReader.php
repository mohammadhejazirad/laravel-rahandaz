<?php

declare(strict_types=1);

namespace App\Infrastructure\Host;

use InvalidArgumentException;

final class AllowlistedHostFileReader implements HostFileReader
{
    /**
     * @param  list<string>  $allow
     */
    public function __construct(private readonly array $allow = ['/etc/os-release', '/proc/meminfo']) {}

    public function read(string $path): ?string
    {
        if (! in_array($path, $this->allow, true)) {
            throw new InvalidArgumentException('Path is not allowlisted.');
        }

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $data = file_get_contents($path, false, null, 0, 65536);

        return $data === false ? null : $data;
    }
}
