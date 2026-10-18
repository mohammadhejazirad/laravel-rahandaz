<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\OperationAccess;
use App\Models\OperationJob;
use App\Models\User;
use App\Models\Workspace;

class OperationJobPolicy
{
    public function __construct(private readonly OperationAccess $access) {}

    public function view(User $user, OperationJob $job): bool
    {
        return $this->access->visibleQuery($user, $job->workspace_id)->whereKey($job->id)->exists();
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $this->access->managesWorkspace($user, $workspace->id);
    }
}
