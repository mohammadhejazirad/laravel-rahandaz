<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Hosts\Enums\FirewallOrigin;
use App\Domain\Hosts\Enums\FirewallState;
use App\Models\FirewallRule;
use App\Models\LocalHost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FirewallRule>
 */
class FirewallRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'direction' => 'in',
            'protocol' => 'tcp',
            'port_start' => 443,
            'source_cidr' => '203.0.113.0/24',
            'purpose' => 'panel https',
            'origin' => FirewallOrigin::SystemDiscovered,
            'state' => FirewallState::Observed,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (FirewallRule $rule): void {
            $rule->local_host_id ??= LocalHost::factory()->create()->id;
        });
    }
}
