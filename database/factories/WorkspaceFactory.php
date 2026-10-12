<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'راه‌انداز',
            'slug' => 'ws'.bin2hex(random_bytes(4)),
            'owner_id' => User::factory(),
            'settings' => [],
            'setup_completed_at' => null,
        ];
    }
}
