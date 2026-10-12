<?php

namespace Database\Factories;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'role' => MembershipRole::Viewer,
        ];
    }
}
