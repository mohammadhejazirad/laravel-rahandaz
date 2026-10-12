<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RemoteBranch;
use App\Models\RemoteRepository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RemoteBranch>
 */
class RemoteBranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'main',
            'head_sha' => str_repeat('ab', 20),
            'observed_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (RemoteBranch $branch): void {
            $branch->remote_repository_id ??= RemoteRepository::factory()->create()->id;
        });
    }
}
