<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Hosts\Enums\HostCompatibility;
use App\Models\HostFact;
use App\Models\LocalHost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HostFact>
 */
class HostFactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'observed_at' => now(),
            'os_name' => 'Ubuntu',
            'os_version' => '24.04',
            'os_codename' => 'noble',
            'architecture' => 'x86_64',
            'cpu_cores' => 2,
            'compatibility' => HostCompatibility::Untested,
            'collector' => 'readonly_probe',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (HostFact $fact): void {
            $fact->local_host_id ??= LocalHost::factory()->create()->id;
        });
    }
}
