<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Accounts\Enums\UserStatus;
use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Git\GitConnector;
use App\Models\GitConnection;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class SyncGitRepositories implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 120;

    public bool $failOnTimeout = true;

    public function __construct(public string $connectionUuid, public string $actorUuid)
    {
        $this->onQueue('control');
    }

    public function uniqueId(): string
    {
        return $this->connectionUuid;
    }

    public function handle(GitConnector $connector, AuditRecorder $audit): void
    {
        $connection = GitConnection::query()->where('uuid', $this->connectionUuid)->firstOrFail();
        $actor = User::query()->where('uuid', $this->actorUuid)->firstOrFail();
        if ($actor->status !== UserStatus::Active) {
            throw new \RuntimeException('Git synchronization actor is inactive.');
        }
        Gate::forUser($actor)->authorize('update', $connection);
        $audit->record($connection->workspace, $actor, 'git.sync_started', 'git_connection', $connection->uuid, AuditResult::Success);
        try {
            $connector->syncRepositories($connection, $actor, null, null);
        } catch (Throwable) {
            // Queue failure records must never contain provider responses or headers.
            throw new \RuntimeException('Git repository synchronization failed.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        $connection = GitConnection::query()->where('uuid', $this->connectionUuid)->first();
        if ($connection !== null) {
            app(AuditRecorder::class)->record($connection->workspace, User::query()->where('uuid', $this->actorUuid)->first(), 'git.sync_failed', 'git_connection', $connection->uuid, AuditResult::Failure);
        }
    }
}
