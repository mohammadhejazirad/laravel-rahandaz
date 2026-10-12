<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Hosts\Enums\HostCompatibility;
use App\Domain\Hosts\Enums\HostState;
use App\Models\LocalHost;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocalHost>
 */
class LocalHostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => 'local-host',
            'display_name' => 'میزبان محلی',
            'hostname' => 'localhost',
            'state' => HostState::PendingProbe,
            'compatibility' => HostCompatibility::Untested,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (LocalHost $host): void {
            if ($host->workspace_id !== null) {
                return;
            }

            $owner = User::factory()->create();
            $host->workspace_id = Workspace::factory()->create(['owner_id' => $owner->id])->id;
        });
    }
}
