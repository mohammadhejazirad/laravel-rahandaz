<?php

declare(strict_types=1);

namespace App\Domain\Hosts;

use App\Domain\Access\OperationAccess;
use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\RecordOperation;
use App\Models\LocalHost;
use App\Models\OperationJob;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class HostServiceControl
{
    public function __construct(private readonly HostServiceCatalog $catalog) {}

    public function request(Workspace $workspace, LocalHost $host, User $actor, string $unit, string $action): OperationJob
    {
        abort_unless((string) $host->workspace_id === (string) $workspace->id && $host->slug === 'local-host', 404);
        abort_unless(app(OperationAccess::class)->managesWorkspace($actor, $workspace->id), 403);
        $service = $this->catalog->find($unit);
        if ($service === null || ! in_array($action, $service['actions'], true)) {
            throw ValidationException::withMessages(['action' => 'این عمل برای این سرویس مجاز نیست.']);
        }

        return DB::transaction(function () use ($workspace, $host, $actor, $unit, $action): OperationJob {
            $type = 'service.'.$action;
            $job = app(RecordOperation::class)->record(new RecordOperationData(
                workspaceId: $workspace->id,
                actorId: $actor->id,
                type: $type,
                idempotencyKey: 'service:'.$host->uuid.':'.$unit.':'.$action.':'.Str::uuid()->toString(),
                plan: [
                    'contract_version' => '1',
                    'unit' => $unit,
                    'operation' => $type,
                    'host_uuid' => $host->uuid,
                    'approval' => $action !== 'status',
                ],
                mutatesHost: $action !== 'status',
                resourceType: 'local_host',
                resourceId: $host->id,
            ));
            app(AuditRecorder::class)->record(
                $workspace,
                $actor,
                'service.'.$action.'_requested',
                'local_host',
                $host->uuid,
                AuditResult::Success,
                ['unit' => $unit, 'host_executed' => false],
                operationJob: $job,
            );

            return $job;
        });
    }
}
