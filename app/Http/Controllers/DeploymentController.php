<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Projects\DeploymentWorkflow;
use App\Domain\Projects\Exceptions\DeploymentRejected;
use App\Http\CurrentWorkspace;
use App\Http\HostMutation;
use App\Http\PaginatorPayload;
use App\Http\Requests\DeploymentStoreRequest;
use App\Http\WorkspaceAccess;
use App\Models\Deployment;
use App\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class DeploymentController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = CurrentWorkspace::get($request);
        $filters = $request->validate(['status' => ['nullable', 'in:queued,running,verifying,rolling_back,succeeded,failed,cancelled'], 'environment' => ['nullable', 'uuid:7']]);
        $rows = Deployment::query()->forWorkspace($workspace->id)->with('environment.project', 'operationJob')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['environment'] ?? null, fn ($q, $uuid) => $q->whereHas('environment', fn ($e) => $e->where('uuid', $uuid)))
            ->orderByDesc('id')->paginate(20)->withQueryString();
        $options = Environment::query()->forWorkspace($workspace->id)->with('project')->orderBy('name')->limit(100)->get();

        return Inertia::render('deployments/index', [
            'deployments' => ['data' => $rows->getCollection()->map(fn (Deployment $d): array => $this->summary($d))->all(), 'meta' => PaginatorPayload::meta($rows)],
            'environments' => $options->map(fn (Environment $e): array => ['uuid' => $e->uuid, 'name' => $e->project?->name.' / '.$e->name, 'branch' => $e->branch_name])->all(),
            'can_operate' => in_array(WorkspaceAccess::membership($request->user(), $workspace)->role->value, ['owner', 'admin', 'operator'], true),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, Deployment $deployment, DeploymentWorkflow $workflow): Response
    {
        $this->scope($request, $deployment);
        $deployment->load('environment.project', 'environment.activeRelease', 'operationJob', 'previousRelease', 'targetRelease');

        return Inertia::render('deployments/show', [
            'deployment' => [...$this->summary($deployment), 'build_status' => $deployment->build_status->value, 'health_status' => $deployment->health_status->value,
                'rollback_status' => $deployment->rollback_status->value, 'active_release_uuid' => $deployment->environment?->activeRelease?->uuid,
                'previous_release_uuid' => $deployment->previousRelease?->uuid, 'target_release_uuid' => $deployment->targetRelease?->uuid],
            'can_operate' => in_array(WorkspaceAccess::membership($request->user(), CurrentWorkspace::get($request))->role->value, ['owner', 'admin', 'operator'], true),
            'rollback_block_reason' => $workflow->rollbackBlockReason($deployment),
        ]);
    }

    public function store(DeploymentStoreRequest $request, DeploymentWorkflow $workflow): RedirectResponse
    {
        $workspace = CurrentWorkspace::get($request);
        $environment = Environment::query()->forWorkspace($workspace->id)->where('uuid', $request->validated('environment_uuid'))->firstOrFail();
        $this->authorize('deploy', $environment->project);
        try {
            $queued = $workflow->queueManual($environment, $request->user(), $request->validated('commit_sha'), $request->attributes->get('request_id'), $request->ip(), $request->validated('idempotency_key'));
        } catch (DeploymentRejected $e) {
            throw ValidationException::withMessages(['commit_sha' => $e->getMessage()]);
        }

        return redirect()->route('deployments.show', $queued->deployment)->with('status', HostMutation::queuedMessage());
    }

    public function action(Request $request, Deployment $deployment, string $action, DeploymentWorkflow $workflow): RedirectResponse
    {
        $this->scope($request, $deployment);
        WorkspaceAccess::assertOperates($request->user(), CurrentWorkspace::get($request));
        $this->authorize('deploy', $deployment->environment->project);
        try {
            if ($action === 'cancel') {
                $result = $workflow->cancel($deployment, $request->user(), $request->attributes->get('request_id'), $request->ip());

                return back()->with('status', $result->status->value === 'cancelled' ? 'استقرار شروع‌نشده لغو شد.' : 'درخواست لغو ثبت شد؛ توقف فرایند هنوز تأیید نشده است.');
            }
            abort_unless($action === 'retry', 404);
            $queued = $workflow->retry($deployment, $request->user(), $request->attributes->get('request_id'), $request->ip());

            return redirect()->route('deployments.show', $queued->deployment)->with('status', HostMutation::queuedMessage());
        } catch (DeploymentRejected $e) {
            throw ValidationException::withMessages(['operation' => $e->getMessage()]);
        }
    }

    private function scope(Request $request, Deployment $deployment): void
    {
        abort_unless((string) $deployment->workspace_id === (string) CurrentWorkspace::get($request)->id, 404);
        $this->authorize('view', $deployment->environment->project);
    }

    private function summary(Deployment $d): array
    {
        return ['uuid' => $d->uuid, 'project_name' => $d->environment?->project?->name, 'environment_name' => $d->environment?->name,
            'commit_sha' => $d->commit_sha, 'artifact_digest' => $d->artifact_digest, 'source_type' => $d->source_type->value,
            'status' => $d->status->value, 'trigger' => $d->trigger_type->value, 'failure_code' => $d->failure_code,
            'job_uuid' => $d->operationJob?->uuid, 'job_status' => $d->operationJob?->status->value,
            'created_at' => $d->created_at?->utc()->toIso8601ZuluString(), 'finished_at' => $d->finished_at?->utc()->toIso8601ZuluString()];
    }
}
