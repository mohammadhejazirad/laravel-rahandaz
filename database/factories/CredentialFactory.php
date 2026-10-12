<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Secrets\CredentialStore;
use App\Models\Credential;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Credential>
 */
class CredentialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'github',
            'label' => 'factory',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Credential $credential): void {
            if ($credential->envelope !== null) {
                return;
            }

            if ($credential->workspace_id === null) {
                $owner = User::factory()->create();
                $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
                $credential->workspace_id = $workspace->id;
                $credential->created_by ??= $owner->id;
            }

            $workspace = Workspace::query()->findOrFail($credential->workspace_id);
            $actorId = $credential->created_by ?? $workspace->owner_id;
            $actor = User::query()->findOrFail($actorId);
            $type = (string) ($credential->type ?? 'github');
            $material = app(CredentialStore::class)->material(
                $workspace,
                $actor,
                $type,
                'factory-secret-'.bin2hex(random_bytes(8)),
                $credential->label,
            );

            $credential->forceFill($material);
        });
    }
}
