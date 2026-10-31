<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Access\OperationAccess;
use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Hosts\PhpServerProfileRules;
use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\RecordOperation;
use App\Http\CurrentWorkspace;
use App\Http\HostMutation;
use App\Models\LocalHost;
use App\Models\PhpServerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PhpServerController extends Controller
{
    public function show(Request $request): Response
    {
        $workspace = CurrentWorkspace::get($request);
        $profile = PhpServerProfile::query()->where('workspace_id', $workspace->id)->first();

        return Inertia::render('servers/php', [
            'can_request' => app(OperationAccess::class)->managesWorkspace($request->user(), $workspace->id),
            'profile' => [
                'upload_max_filesize' => $profile->upload_max_filesize ?? '8M',
                'post_max_size' => $profile->post_max_size ?? '8M',
                'memory_limit' => $profile->memory_limit ?? '128M',
                'max_execution_time' => $profile->max_execution_time ?? 30,
                'max_input_time' => $profile->max_input_time ?? 60,
                'max_input_vars' => $profile->max_input_vars ?? 1000,
                'timezone' => $profile->timezone ?? 'Asia/Tehran',
            ],
        ]);
    }

    public function update(Request $request, PhpServerProfileRules $rules): RedirectResponse
    {
        $workspace = CurrentWorkspace::get($request);
        abort_unless(app(OperationAccess::class)->managesWorkspace($request->user(), $workspace->id), 403);
        $host = LocalHost::query()->forWorkspace($workspace->id)->where('slug', 'local-host')->firstOrFail();
        $values = $rules->normalize($request->validate([
            'upload_max_filesize' => ['required', 'string', 'max:8'],
            'post_max_size' => ['required', 'string', 'max:8'],
            'memory_limit' => ['required', 'string', 'max:8'],
            'max_execution_time' => ['required', 'integer'],
            'max_input_time' => ['required', 'integer'],
            'max_input_vars' => ['required', 'integer'],
            'timezone' => ['required', 'string', 'max:64'],
        ]));

        DB::transaction(function () use ($workspace, $host, $request, $values): void {
            $profile = PhpServerProfile::query()->updateOrCreate(
                ['workspace_id' => $workspace->id],
                ['local_host_id' => $host->id, ...$values],
            );
            $job = app(RecordOperation::class)->record(new RecordOperationData(
                workspaceId: $workspace->id,
                actorId: $request->user()->id,
                type: 'php.ini.apply',
                idempotencyKey: 'php-ini:'.$host->uuid.':'.Str::uuid()->toString(),
                plan: ['contract_version' => '1', 'directives' => $values, 'host_executed' => false],
                mutatesHost: true,
                resourceType: 'local_host',
                resourceId: $host->id,
            ));
            app(AuditRecorder::class)->record($workspace, $request->user(), 'php.profile_requested', 'local_host', $host->uuid, AuditResult::Success, [
                'profile_uuid' => $profile->uuid,
                'host_executed' => false,
            ], operationJob: $job);
        });

        return back()->with('status', HostMutation::queuedMessage());
    }
}
