<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Projects\Enums\ProjectSourceArchiveStatus;
use App\Models\Project;
use App\Models\ProjectSourceArchive;
use App\Models\User;
use App\Support\Identifiers;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectSourceArchive>
 */
class ProjectSourceArchiveFactory extends Factory
{
    public function definition(): array
    {
        $digest = hash('sha256', fake()->uuid());

        return [
            'disk' => 'project_archives',
            'original_name' => 'project.zip',
            'media_type' => 'application/zip',
            'size_bytes' => 1024,
            'sha256' => $digest,
            'entry_count' => 2,
            'uncompressed_bytes' => 2048,
            'status' => ProjectSourceArchiveStatus::Ready,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ProjectSourceArchive $archive): void {
            if ($archive->project_id === null) {
                $project = Project::factory()->create(
                    $archive->workspace_id === null ? [] : ['workspace_id' => $archive->workspace_id],
                );
                $archive->project_id = $project->id;
                $archive->workspace_id = $project->workspace_id;
            }

            $project = Project::query()->findOrFail($archive->project_id);
            $archive->workspace_id ??= $project->workspace_id;
            $archive->uploaded_by ??= User::factory()->create()->id;
            $archive->uuid ??= Identifiers::uuid7();
            $archive->path ??= 'workspaces/'.$project->workspace->uuid.'/projects/'.$project->uuid.'/'.$archive->uuid.'.zip';
        });
    }
}
