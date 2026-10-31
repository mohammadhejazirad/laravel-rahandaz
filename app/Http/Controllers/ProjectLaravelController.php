<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Access\OperationAccess;
use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\RecordOperation;
use App\Domain\Projects\LaravelRuntimeSettings;
use App\Http\CurrentWorkspace;
use App\Http\HostMutation;
use App\Models\Project;
use App\Models\ProjectLaravelSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectLaravelController extends Controller
{
    public function update(Request $request, Project $project, LaravelRuntimeSettings $settings): RedirectResponse
    {
        $this->authorize('view', $project);
        $workspace = CurrentWorkspace::get($request);
        abort_unless((string) $project->workspace_id === (string) $workspace->id, 404);
        abort_unless($project->declared_type->value === 'laravel' || $project->detected_type?->value === 'laravel', 404);
        abort_unless(app(OperationAccess::class)->managesWorkspace($request->user(), $workspace->id), 403);
        $input = $request->validate([
            'feature' => ['required', 'string', 'max:20'],
            'enabled' => ['nullable', 'boolean'],
            'host' => ['nullable', 'string', 'max:253'],
            'port' => ['nullable', 'integer'],
            'server' => ['nullable', 'string', 'max:16'],
            'workers' => ['nullable', 'integer'],
            'path' => ['nullable', 'string', 'max:180'],
        ]);
        $changes = $settings->normalize($input['feature'], $input);

        DB::transaction(function () use ($workspace, $project, $request, $input, $changes): void {
            $row = ProjectLaravelSetting::query()->firstOrCreate(
                ['project_id' => $project->id],
                ['workspace_id' => $workspace->id],
            );
            $row->fill($changes);
            $row->save();
            $job = app(RecordOperation::class)->record(new RecordOperationData(
                workspaceId: $workspace->id,
                actorId: $request->user()->id,
                type: 'project.laravel.apply',
                idempotencyKey: 'laravel-runtime:'.$project->uuid.':'.$input['feature'].':'.Str::uuid()->toString(),
                plan: ['contract_version' => '1', 'feature' => $input['feature'], 'settings' => $changes, 'host_executed' => false],
                mutatesHost: true,
                resourceType: 'project',
                resourceId: $project->id,
            ));
            app(AuditRecorder::class)->record($workspace, $request->user(), 'project.laravel_requested', 'project', $project->uuid, AuditResult::Success, [
                'feature' => $input['feature'],
                'host_executed' => false,
            ], operationJob: $job);
        });

        return back()->with('status', HostMutation::queuedMessage());
    }
}
