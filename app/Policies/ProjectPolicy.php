<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\GrantPermission;
use App\Domain\Access\ProjectAccess;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function __construct(private readonly ProjectAccess $access) {}

    public function view(User $user, Project $project): bool
    {
        return $this->access->query($user, $project->workspace_id)->whereKey($project->id)->exists();
    }

    public function deploy(User $user, Project $project): bool
    {
        return $this->access->query($user, $project->workspace_id, GrantPermission::Deploy)->whereKey($project->id)->exists();
    }
}
