<?php

declare(strict_types=1);

namespace App\Domain\Git;

use App\Domain\Git\Enums\GitConnectionState;
use App\Models\AuditEvent;
use App\Models\GitConnection;

/** Persisted queue progress, independent of the initiating browser request. */
final class GitSyncState
{
    public function pending(GitConnection $connection): bool
    {
        if ($connection->state === GitConnectionState::Revoked) {
            return false;
        }
        $action = AuditEvent::query()->forWorkspace($connection->workspace_id)
            ->where('resource_type', 'git_connection')->where('resource_id', $connection->uuid)
            ->whereIn('action', ['git.sync_requested', 'git.sync_started', 'git.sync', 'git.sync_failed', 'git.revoke'])
            ->latest('id')->value('action');

        return in_array($action, ['git.sync_requested', 'git.sync_started'], true);
    }
}
