<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Git\Enums\RepositoryVisibility;
use App\Models\GitConnection;
use App\Models\RemoteRepository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RemoteRepository>
 */
class RemoteRepositoryFactory extends Factory
{
    public function definition(): array
    {
        $suffix = bin2hex(random_bytes(3));

        return [
            'provider_repository_id' => 'repo'.$suffix,
            'full_name' => 'acme/app-'.$suffix,
            'namespace' => 'acme',
            'clone_url' => 'https://github.com/acme/app-'.$suffix.'.git',
            'default_branch' => 'main',
            'visibility' => RepositoryVisibility::Private,
            'archived' => false,
            'disabled' => false,
            'provider_metadata' => [],
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (RemoteRepository $repository): void {
            if ($repository->git_connection_id === null) {
                $connection = GitConnection::factory()->create(
                    $repository->workspace_id === null ? [] : ['workspace_id' => $repository->workspace_id],
                );
                $repository->git_connection_id = $connection->id;
                $repository->workspace_id = $connection->workspace_id;
            }

            $repository->workspace_id ??= GitConnection::query()->findOrFail($repository->git_connection_id)->workspace_id;
        });
    }
}
