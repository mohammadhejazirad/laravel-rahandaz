<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Backups\BackupWorkflow;
use App\Domain\Backups\Exceptions\BackupRejected;
use App\Http\CurrentWorkspace;
use App\Http\HostMutation;
use App\Http\PaginatorPayload;
use App\Http\Requests\BackupStoreRequest;
use App\Http\WorkspaceAccess;
use App\Models\Backup;
use App\Models\LocalHost;
use App\Models\ProjectDatabase;
use App\Models\Schedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class BackupController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = CurrentWorkspace::get($request);
        abort_unless(WorkspaceAccess::membership($request->user(), $workspace)->role->managesHost(), 403);
        $rows = Backup::query()->forWorkspace($workspace->id)->with('projectDatabase', 'operationJob')->orderByDesc('id')->paginate(20)->withQueryString();
        $targets = LocalHost::query()->forWorkspace($workspace->id)->limit(10)->get()->map(fn (LocalHost $h): array => ['uuid' => $h->uuid, 'type' => 'local_host', 'name' => 'پنل مدیریت / '.$h->display_name])->all();
        $targets = [...$targets, ...ProjectDatabase::query()->forWorkspace($workspace->id)->where('status', 'ready')->where('protected', false)->limit(100)->get()->map(fn (ProjectDatabase $d): array => ['uuid' => $d->uuid, 'type' => 'project_database', 'name' => $d->name])->all()];

        return Inertia::render('backups/index', [
            'request_key' => (string) Str::uuid(),
            'backups' => ['data' => $rows->getCollection()->map(fn (Backup $b): array => $this->summary($b))->all(), 'meta' => PaginatorPayload::meta($rows)],
            'targets' => $targets, 'can_manage' => WorkspaceAccess::membership($request->user(), $workspace)->role->mayDecryptSecrets(),
        ]);
    }

    public function show(Request $request, Backup $backup): Response
    {
        abort_unless((string) $backup->workspace_id === (string) CurrentWorkspace::get($request)->id, 404);
        abort_unless(WorkspaceAccess::membership($request->user(), CurrentWorkspace::get($request))->role->managesHost(), 403);
        $backup->load('projectDatabase', 'operationJob');
        $restores = $backup->restores()->orderByDesc('id')->paginate(20)->withQueryString();

        return Inertia::render('backups/show', [
            'backup' => $this->summary($backup),
            'restores' => ['data' => $restores->getCollection()->map(fn ($r): array => ['uuid' => $r->uuid, 'status' => $r->status, 'approval_state' => $r->approval_state->value, 'failure_code' => $r->failure_code])->all(), 'meta' => PaginatorPayload::meta($restores)],
            'restore_available' => false,
        ]);
    }

    public function store(BackupStoreRequest $request, BackupWorkflow $workflow): RedirectResponse
    {
        $workspace = CurrentWorkspace::get($request);
        abort_unless(WorkspaceAccess::membership($request->user(), $workspace)->role->managesHost(), 403);
        $data = $request->validated();
        try {
            if ($data['target_type'] === 'local_host') {
                $target = LocalHost::query()->forWorkspace($workspace->id)->where('uuid', $data['target_uuid'])->firstOrFail();
                $job = $workflow->queueControlPlane($target, $request->user(), $data['idempotency_key'], $request->attributes->get('request_id'), $request->ip());
            } else {
                $target = ProjectDatabase::query()->forWorkspace($workspace->id)->where('uuid', $data['target_uuid'])->where('protected', false)->firstOrFail();
                $job = $workflow->queueDatabase($target, $request->user(), $data['idempotency_key'], $request->attributes->get('request_id'), $request->ip());
            }
        } catch (BackupRejected $e) {
            throw ValidationException::withMessages(['target_uuid' => $e->getMessage()]);
        }

        return redirect()->route('jobs.show', $job)->with('status', HostMutation::queuedMessage());
    }

    public function schedules(Request $request): Response
    {
        $workspace = CurrentWorkspace::get($request);
        abort_unless(WorkspaceAccess::membership($request->user(), $workspace)->role->managesHost(), 403);
        $rows = Schedule::query()->forWorkspace($workspace->id)->whereIn('operation_key', ['backup.database', 'backup.control_plane'])->orderByDesc('id')->paginate(20)->withQueryString();

        return Inertia::render('backups/schedules', ['schedules' => ['data' => $rows->getCollection()->map(fn (Schedule $s): array => ['uuid' => $s->uuid, 'operation_key' => $s->operation_key, 'cron_expression' => $s->cron_expression, 'timezone' => $s->timezone, 'enabled' => (bool) $s->enabled])->all(), 'meta' => PaginatorPayload::meta($rows)]]);
    }

    private function summary(Backup $b): array
    {
        return ['uuid' => $b->uuid, 'backup_type' => $b->backup_type->value, 'state' => $b->state->value,
            'database_name' => $b->projectDatabase?->name, 'size_bytes' => $b->size_bytes, 'checksum_sha256' => $b->checksum_sha256,
            'encryption_status' => $b->encryption_status, 'verified_at' => $b->verified_at?->utc()->toIso8601ZuluString(),
            'retention_until' => $b->retention_until?->utc()->toIso8601ZuluString(), 'created_at' => $b->created_at?->utc()->toIso8601ZuluString(), 'job_uuid' => $b->operationJob?->uuid];
    }
}
