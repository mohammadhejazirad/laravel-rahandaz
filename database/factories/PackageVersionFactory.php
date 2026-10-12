<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Enums\PackageLifecycle;
use App\Models\PackageDefinition;
use App\Models\PackageVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageVersion>
 */
class PackageVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'version' => '0.0.'.random_int(1, 999),
            'os_codename' => 'noble',
            'architecture' => 'amd64',
            'source_kind' => 'ubuntu_apt',
            'lifecycle' => PackageLifecycle::Untested,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PackageVersion $version): void {
            $version->package_definition_id ??= PackageDefinition::factory()->create()->id;
        });
    }
}
