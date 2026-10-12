<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Mirrors\Enums\TrustStatus;
use App\Models\MirrorEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MirrorEndpoint>
 */
class MirrorEndpointFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider' => 'example',
            'software_kind' => 'composer',
            'country' => 'ir',
            'base_url' => 'https://mirror.example.test/composer-'.bin2hex(random_bytes(3)),
            'trust_status' => TrustStatus::Candidate,
            'capabilities' => ['https' => true],
            'compatible_codenames' => [],
            'priority_seed' => 100,
        ];
    }
}
