<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Jobs\PlanGuard;
use App\Models\AuditEvent;
use App\Models\OperationJob;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Identifiers;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AuditRecorder
{
    public function __construct(private readonly PlanGuard $plans) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        ?Workspace $workspace,
        ?User $actor,
        string $action,
        string $resourceType,
        ?string $resourceId,
        AuditResult $result,
        array $metadata = [],
        ?string $requestId = null,
        ?string $sourceIp = null,
        ?OperationJob $operationJob = null,
    ): AuditEvent {
        if (preg_match('/^[a-z0-9]+(\.[a-z0-9_]+){0,4}$/', $action) !== 1) {
            throw new InvalidArgumentException('Audit action is not a catalog key.');
        }

        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $resourceType) !== 1) {
            throw new InvalidArgumentException('Audit resource type is not a catalog key.');
        }

        if ($resourceId !== null && ! Identifiers::isUuidV7($resourceId)) {
            throw new InvalidArgumentException('Audit resource id must be a UUIDv7.');
        }

        if ($operationJob !== null && $workspace !== null && (string) $operationJob->workspace_id !== (string) $workspace->id) {
            throw new InvalidArgumentException('Audit job is outside the workspace.');
        }

        $this->plans->assertSafe($metadata);

        return DB::transaction(function () use ($workspace, $actor, $action, $resourceType, $resourceId, $result, $metadata, $requestId, $sourceIp, $operationJob): AuditEvent {
            DB::select('select pg_advisory_xact_lock(hashtext(?)::bigint)', [
                'audit:'.($workspace->id ?? 'installation'),
            ]);

            $previous = AuditEvent::query()
                ->when(
                    $workspace === null,
                    fn ($query) => $query->whereNull('workspace_id'),
                    fn ($query) => $query->where('workspace_id', $workspace->id),
                )
                ->orderByDesc('recorded_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $uuid = Identifiers::uuid7();
            $recordedAt = now()->utc();
            $prevHash = $previous?->event_hash;
            $eventHash = hash('sha256', $this->plans->canonical([
                'action' => $action,
                'actor_id' => $actor?->id,
                'metadata' => $metadata,
                'operation_job_id' => $operationJob?->id,
                'prev_hash' => $prevHash,
                'recorded_at' => $recordedAt->format('Y-m-d\TH:i:s.uP'),
                'resource_id' => $resourceId,
                'resource_type' => $resourceType,
                'result' => $result->value,
                'uuid' => $uuid,
                'workspace_id' => $workspace?->id,
            ]));

            $event = new AuditEvent;
            $event->forceFill([
                'workspace_id' => $workspace?->id,
                'actor_id' => $actor?->id,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'source_ip' => $sourceIp,
                'request_id' => $requestId,
                'operation_job_id' => $operationJob?->id,
                'result' => $result,
                'metadata' => $metadata,
                'prev_hash' => $prevHash,
                'event_hash' => $eventHash,
                'recorded_at' => $recordedAt,
            ]);
            $event->uuid = $uuid;
            $event->save();

            return $event;
        });
    }
}
