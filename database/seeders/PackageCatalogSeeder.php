<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PackageDefinition;
use App\Models\PackageVersion;
use Illuminate\Database\Seeder;

/**
 * Non-sensitive package catalog. Versions stay untested until a VM proves them.
 */
class PackageCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            ['key' => 'nginx', 'display_name' => 'Nginx', 'service_kind' => 'web', 'control_plane_candidate' => true],
            ['key' => 'php', 'display_name' => 'PHP', 'service_kind' => 'runtime', 'control_plane_candidate' => false],
            ['key' => 'node', 'display_name' => 'Node.js', 'service_kind' => 'runtime', 'control_plane_candidate' => false],
            ['key' => 'python', 'display_name' => 'Python3', 'service_kind' => 'runtime', 'control_plane_candidate' => false],
            ['key' => 'postgresql', 'display_name' => 'PostgreSQL', 'service_kind' => 'database', 'control_plane_candidate' => true],
            ['key' => 'redis', 'display_name' => 'Redis', 'service_kind' => 'cache', 'control_plane_candidate' => true],
            ['key' => 'mysql', 'display_name' => 'MySQL', 'service_kind' => 'database', 'control_plane_candidate' => false],
            ['key' => 'mariadb', 'display_name' => 'MariaDB', 'service_kind' => 'database', 'control_plane_candidate' => false],
            ['key' => 'mongodb', 'display_name' => 'MongoDB', 'service_kind' => 'database', 'control_plane_candidate' => false],
            ['key' => 'docker', 'display_name' => 'Docker', 'service_kind' => 'runtime', 'control_plane_candidate' => false],
            ['key' => 'google-chrome', 'display_name' => 'Google Chrome', 'service_kind' => 'tool', 'control_plane_candidate' => false],
        ];

        foreach ($packages as $package) {
            $definition = PackageDefinition::query()->updateOrCreate(
                ['key' => $package['key']],
                [
                    'display_name' => $package['display_name'],
                    'service_kind' => $package['service_kind'],
                    'capability_flags' => [],
                    'conflicts' => [],
                    'definition_version' => 1,
                    'control_plane_candidate' => $package['control_plane_candidate'],
                ],
            );

            if ($package['key'] === 'google-chrome') {
                PackageVersion::query()->where('package_definition_id', $definition->id)->where('version', '!=', 'latest')->delete();
                foreach (['jammy', 'noble'] as $codename) {
                    PackageVersion::query()->updateOrCreate(
                        [
                            'package_definition_id' => $definition->id,
                            'version' => 'latest',
                            'os_codename' => $codename,
                            'architecture' => 'amd64',
                        ],
                        [
                            'source_kind' => 'upstream_tarball',
                            'lifecycle' => 'untested',
                            'checksum_sha256' => null,
                        ],
                    );
                }

                continue;
            }

            PackageVersion::query()->updateOrCreate(
                [
                    'package_definition_id' => $definition->id,
                    'version' => 'untested',
                    'os_codename' => 'noble',
                    'architecture' => 'amd64',
                ],
                [
                    'source_kind' => 'ubuntu_apt',
                    'lifecycle' => 'untested',
                    'checksum_sha256' => null,
                ],
            );
        }
    }
}
