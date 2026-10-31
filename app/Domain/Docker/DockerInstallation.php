<?php

declare(strict_types=1);

namespace App\Domain\Docker;

final class DockerInstallation
{
    public function present(): bool
    {
        $path = '/usr/bin/docker';

        return is_file($path) && is_executable($path);
    }
}
