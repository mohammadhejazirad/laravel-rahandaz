<?php

declare(strict_types=1);

namespace App\Domain\Access;

use App\Domain\Accounts\Enums\UserStatus;
use App\Models\Environment;
use App\Models\Membership;
use App\Models\OperationJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class OperationAccess
{
    public function managesWorkspace(User $user, int|string $workspaceId): bool
    {
        return $user->status === UserStatus::Active && Membership::query()
            ->where('workspace_id', $workspaceId)->where('user_id', $user->id)->first()?->role->managesHost() === true;
    }

    /** @return Builder<OperationJob> */
    public function visibleQuery(User $user, int|string $workspaceId): Builder
    {
        $query = OperationJob::query()->where('workspace_id', $workspaceId);

        // Logs need an independent permission contract before delegation is enabled.
        return $this->managesWorkspace($user, $workspaceId) ? $query : $query->whereRaw('false');
    }

    public function mayExecute(OperationJob $job): bool
    {
        // Never trust a serialized actor, prior HTTP check, or cached relationship.
        $actor = User::query()->find($job->actor_id);
        if ($actor === null || $actor->status !== UserStatus::Active) {
            return false;
        }

        if (in_array($job->type, ['deploy.prepare', 'deploy.archive', 'deploy.rollback', 'nginx.site.apply', 'nginx.site.remove', 'certificate.issue'], true)) {
            if ($job->resource_type !== 'environment') {
                return false;
            }
            $environment = Environment::query()->forWorkspace($job->workspace_id)->find($job->resource_id);

            return $environment !== null && $environment->project !== null
                && Gate::forUser($actor)->allows('deploy', $environment->project);
        }

        // Unknown or host-level operations cannot inherit a project grant.
        return $this->managesWorkspace($actor, $job->workspace_id);
    }
}
