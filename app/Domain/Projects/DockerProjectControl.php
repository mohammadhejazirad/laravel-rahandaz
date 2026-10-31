<?php

declare(strict_types=1);

namespace App\Domain\Projects;

use App\Domain\Access\OperationAccess;
use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\RecordOperation;
use App\Models\OperationJob;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DockerProjectControl
{
    /**
     * @param  list<string>  $filenames
     */
    public function request(Workspace $workspace, Project $project, User $actor, string $layout, string $action, array $filenames, ?string $service, ?string $composeFile, ?string $dockerfile): OperationJob
    {
        abort_unless((string) $project->workspace_id === (string) $workspace->id, 404);
        abort_unless(app(OperationAccess::class)->managesWorkspace($actor, $workspace->id), 403);
        if ($filenames !== [] && DockerLayout::classify($filenames) !== $layout) {
            throw ValidationException::withMessages(['layout' => 'فایل‌های اعلام‌شده با نوع Docker انتخاب‌شده یکی نیست.']);
        }
        if (! in_array($action, DockerLayout::actions($layout), true) || DockerLayout::operation($action) === null) {
            throw ValidationException::withMessages(['action' => 'این عمل با نوع فایل‌های Docker پروژه جور نیست.']);
        }
        $service = $service !== null && $service !== '' ? $service : null;
        if ($service !== null && preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/', $service) !== 1) {
            throw ValidationException::withMessages(['service' => 'نام سرویس Compose مجاز نیست.']);
        }
        $composeFile = $composeFile !== null && $composeFile !== '' ? $composeFile : 'compose.yml';
        $dockerfile = $dockerfile !== null && $dockerfile !== '' ? $dockerfile : 'Dockerfile';
        if (preg_match('/^(docker-compose|compose)\.ya?ml$/', $composeFile) !== 1) {
            throw ValidationException::withMessages(['compose_file' => 'فقط نام فایل Compose در ریشه پذیرفته می‌شود.']);
        }
        if (preg_match('/^Dockerfile(\.[A-Za-z0-9._-]{1,32})?$/', $dockerfile) !== 1) {
            throw ValidationException::withMessages(['dockerfile' => 'فقط نام Dockerfile در ریشه پذیرفته می‌شود.']);
        }
        if (str_starts_with($project->slug, 'rahandaz')) {
            throw ValidationException::withMessages(['project' => 'این نام پروژه برای راه‌انداز رزرو است.']);
        }

        return DB::transaction(function () use ($workspace, $project, $actor, $layout, $action, $service, $composeFile, $dockerfile): OperationJob {
            $type = DockerLayout::operation($action);
            $job = app(RecordOperation::class)->record(new RecordOperationData(
                workspaceId: $workspace->id,
                actorId: $actor->id,
                type: (string) $type,
                idempotencyKey: 'docker:'.$project->uuid.':'.$action.':'.Str::uuid()->toString(),
                plan: [
                    'contract_version' => '1',
                    'project_slug' => $project->slug,
                    'layout' => $layout,
                    'compose_file' => $composeFile,
                    'dockerfile' => $dockerfile,
                    'service' => $service ?? '',
                    'approval' => $action !== 'status',
                    'host_executed' => false,
                ],
                mutatesHost: $action !== 'status',
                resourceType: 'project',
                resourceId: $project->id,
            ));
            app(AuditRecorder::class)->record(
                $workspace,
                $actor,
                'docker.'.$action.'_requested',
                'project',
                $project->uuid,
                AuditResult::Success,
                ['layout' => $layout, 'host_executed' => false],
                operationJob: $job,
            );

            return $job;
        });
    }
}
