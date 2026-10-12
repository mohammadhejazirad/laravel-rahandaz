<?php

namespace Database\Factories;

use App\Domain\Accounts\Enums\Theme;
use App\Domain\Accounts\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'username' => 'user_'.bin2hex(random_bytes(4)),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Password-test-123',
            'status' => UserStatus::Active,
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'theme' => Theme::System,
        ];
    }
}
