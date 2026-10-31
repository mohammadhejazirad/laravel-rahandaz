<?php

declare(strict_types=1);

namespace App\Domain\Projects;

final class DockerLayout
{
    public const COMPOSE_ONLY = 'compose_only';

    public const DOCKERFILE_ONLY = 'dockerfile_only';

    public const BOTH = 'compose_and_dockerfile';

    public const ABSENT = 'absent';

    /**
     * @param  list<string>  $names
     */
    public static function classify(array $names): string
    {
        $compose = false;
        $dockerfile = false;
        foreach ($names as $name) {
            $base = basename(str_replace('\\', '/', $name));
            if ($base !== $name && str_contains($name, '/') || str_contains($name, '\\')) {
                continue;
            }
            if (preg_match('/^(docker-compose|compose)\.ya?ml$/', $base) === 1) {
                $compose = true;
            }
            if (preg_match('/^Dockerfile(\.[A-Za-z0-9._-]{1,32})?$/', $base) === 1) {
                $dockerfile = true;
            }
        }

        return match (true) {
            $compose && $dockerfile => self::BOTH,
            $compose => self::COMPOSE_ONLY,
            $dockerfile => self::DOCKERFILE_ONLY,
            default => self::ABSENT,
        };
    }

    /**
     * @return list<string>
     */
    public static function actions(string $layout): array
    {
        return match ($layout) {
            self::COMPOSE_ONLY => ['status', 'up', 'stop', 'restart', 'down'],
            self::DOCKERFILE_ONLY => ['status', 'build'],
            self::BOTH => ['status', 'up', 'stop', 'restart', 'down', 'build'],
            default => [],
        };
    }

    public static function operation(string $action): ?string
    {
        return match ($action) {
            'status' => 'docker.compose.status',
            'up' => 'docker.compose.up',
            'stop' => 'docker.compose.stop',
            'restart' => 'docker.compose.restart',
            'down' => 'docker.compose.down',
            'build' => 'docker.image.build',
            default => null,
        };
    }
}
