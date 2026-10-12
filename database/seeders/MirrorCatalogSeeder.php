<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MirrorEndpoint;
use Illuminate\Database\Seeder;

/**
 * Candidate mirror URLs from the product spec. Nothing here is marked trusted.
 */
class MirrorCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $endpoints = [
            ['provider' => 'iranserver', 'software_kind' => 'ubuntu_apt', 'base_url' => 'https://mirror.iranserver.com/ubuntu/', 'priority_seed' => 10],
            ['provider' => 'ubuntu-archive-ir', 'software_kind' => 'ubuntu_apt', 'base_url' => 'https://ir.archive.ubuntu.com/ubuntu/', 'priority_seed' => 20],
            ['provider' => 'mobinhost', 'software_kind' => 'ubuntu_apt', 'base_url' => 'https://ubuntu.mobinhost.com/ubuntu/', 'priority_seed' => 30],
            ['provider' => 'pishgaman', 'software_kind' => 'ubuntu_apt', 'base_url' => 'https://ubuntu.pishgaman.net/ubuntu/', 'priority_seed' => 40],
            ['provider' => 'sindad', 'software_kind' => 'ubuntu_apt', 'base_url' => 'https://ir.ubuntu.sindad.cloud/ubuntu/', 'priority_seed' => 50],
            ['provider' => 'arvancloud', 'software_kind' => 'ubuntu_apt', 'base_url' => 'https://mirror.arvancloud.ir/ubuntu/', 'priority_seed' => 60],
            ['provider' => 'arvancloud', 'software_kind' => 'docker_registry', 'base_url' => 'https://docker.arvancloud.ir', 'priority_seed' => 10],
            ['provider' => 'docker-ir', 'software_kind' => 'docker_registry', 'base_url' => 'https://registry.docker.ir', 'priority_seed' => 20],
            ['provider' => 'arvancloud', 'software_kind' => 'npm', 'base_url' => 'https://npm.arvancloud.ir', 'priority_seed' => 10],
            ['provider' => 'arvancloud', 'software_kind' => 'composer', 'base_url' => 'https://composer.arvancloud.ir', 'priority_seed' => 10],
        ];

        foreach ($endpoints as $endpoint) {
            MirrorEndpoint::query()->updateOrCreate(
                [
                    'software_kind' => $endpoint['software_kind'],
                    'base_url' => $endpoint['base_url'],
                ],
                [
                    'provider' => $endpoint['provider'],
                    'country' => 'ir',
                    'trust_status' => 'candidate',
                    'capabilities' => ['https' => true],
                    'compatible_codenames' => [],
                    'priority_seed' => $endpoint['priority_seed'],
                    'disabled_reason' => null,
                ],
            );
        }
    }
}
