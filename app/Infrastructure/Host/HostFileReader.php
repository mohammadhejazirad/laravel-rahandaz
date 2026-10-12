<?php

declare(strict_types=1);

namespace App\Infrastructure\Host;

interface HostFileReader
{
    public function read(string $path): ?string;
}
