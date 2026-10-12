<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Enums\ServiceKind;
use App\Models\PackageDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageDefinition>
 */
class PackageDefinitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => 'pkg-'.bin2hex(random_bytes(4)),
            'display_name' => 'Package',
            'service_kind' => ServiceKind::Tool,
            'capability_flags' => [],
            'conflicts' => [],
            'definition_version' => 1,
            'control_plane_candidate' => false,
        ];
    }
}
