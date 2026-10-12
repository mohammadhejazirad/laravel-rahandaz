<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Git\Enums\GitAuthMethod;
use App\Domain\Git\Enums\GitConnectionState;
use App\Domain\Git\Enums\GitProvider;
use App\Models\Credential;
use App\Models\GitConnection;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GitConnection>
 */
class GitConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider' => GitProvider::Github,
            'auth_method' => GitAuthMethod::PersonalAccessToken,
            'account_label' => 'GitHub',
            'external_account_id' => 'acct'.bin2hex(random_bytes(4)),
            'external_account_type' => 'user',
            'base_url' => 'https://github.com',
            'scopes' => ['repo'],
            'state' => GitConnectionState::Active,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (GitConnection $connection): void {
            if ($connection->workspace_id === null) {
                $owner = User::factory()->create();
                $connection->workspace_id = Workspace::factory()->create(['owner_id' => $owner->id])->id;
            }

            if ($connection->credential_id !== null) {
                return;
            }

            $workspace = Workspace::query()->findOrFail($connection->workspace_id);
            $connection->credential_id = Credential::factory()->create([
                'workspace_id' => $workspace->id,
                'created_by' => $workspace->owner_id,
                'type' => 'github',
            ])->id;
        });
    }
}
