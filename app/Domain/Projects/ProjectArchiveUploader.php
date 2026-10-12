<?php

declare(strict_types=1);

namespace App\Domain\Projects;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\RecordOperation;
use App\Domain\Projects\Data\StoredArchiveDeployment;
use App\Domain\Projects\Enums\ProjectSourceArchiveStatus;
use App\Models\Environment;
use App\Models\Project;
use App\Models\ProjectSourceArchive;
use App\Models\User;
use App\Support\Identifiers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ProjectArchiveUploader
{
    public function __construct(
        private readonly ArchiveInspector $inspector,
        private readonly DeploymentRecorder $deployments,
        private readonly RecordOperation $operations,
        private readonly AuditRecorder $audit,
    ) {}

    public function storeAndQueue(
        UploadedFile $file,
        Project $project,
        Environment $environment,
        User $actor,
        ?string $requestId,
        ?string $sourceIp,
    ): StoredArchiveDeployment {
        $inspection = $this->inspector->inspect($file->getPathname());
        $disk = (string) config('rahandaz.project_archives.disk');
        $archiveUuid = Identifiers::uuid7();
        $path = 'workspaces/'.$project->workspace->uuid.'/projects/'.$project->uuid.'/'.$archiveUuid.'.zip';
        $storedPath = Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));

        if ($storedPath !== $path) {
            if (is_string($storedPath) && $storedPath !== '') {
                Storage::disk($disk)->delete($storedPath);
            }

            throw new \RuntimeException('Archive could not be stored at the expected private path.');
        }

        $keepStoredFile = false;

        try {
            $result = DB::transaction(function () use ($inspection, $disk, $path, $archiveUuid, $file, $project, $environment, $actor, $requestId, $sourceIp, &$keepStoredFile): StoredArchiveDeployment {
                DB::select('select pg_advisory_xact_lock(hashtext(?)::bigint)', [
                    'project-archive:'.$project->id.':'.$inspection->sha256,
                ]);

                $archive = ProjectSourceArchive::query()
                    ->where('project_id', $project->id)
                    ->where('sha256', $inspection->sha256)
                    ->where('status', ProjectSourceArchiveStatus::Ready->value)
                    ->lockForUpdate()
                    ->first();
                $reusedArchive = $archive !== null;

                if ($archive === null) {
                    $archive = new ProjectSourceArchive;
                    $archive->uuid = $archiveUuid;
                    $archive->fill([
                        'workspace_id' => $project->workspace_id,
                        'project_id' => $project->id,
                        'uploaded_by' => $actor->id,
                        'disk' => $disk,
                        'path' => $path,
                        'original_name' => $this->safeOriginalName($file),
                        'media_type' => $file->getMimeType() ?: 'application/zip',
                        'size_bytes' => $inspection->sizeBytes,
                        'sha256' => $inspection->sha256,
                        'entry_count' => $inspection->entryCount,
                        'uncompressed_bytes' => $inspection->uncompressedBytes,
                        'status' => ProjectSourceArchiveStatus::Ready,
                    ]);
                    $archive->save();
                    $keepStoredFile = true;
                } else {
                    Storage::disk($disk)->delete($path);
                }

                $deployment = $this->deployments->queueArchive($environment, $actor, $archive);
                $operation = $this->operations->record(new RecordOperationData(
                    workspaceId: $project->workspace_id,
                    actorId: $actor->id,
                    type: 'deploy.archive',
                    idempotencyKey: 'deployment:'.$deployment->uuid,
                    plan: [
                        'source_type' => 'archive',
                        'archive_uuid' => $archive->uuid,
                        'archive_sha256' => $archive->sha256,
                        'project_uuid' => $project->uuid,
                        'environment_uuid' => $environment->uuid,
                    ],
                    mutatesHost: true,
                    resourceType: 'environment',
                    resourceId: $environment->id,
                ));

                $deployment->operation_job_id = $operation->id;
                $deployment->save();

                $this->audit->record(
                    $project->workspace,
                    $actor,
                    'project.archive_upload',
                    'project_source_archive',
                    $archive->uuid,
                    AuditResult::Success,
                    [
                        'deployment_uuid' => $deployment->uuid,
                        'environment_uuid' => $environment->uuid,
                        'operation_job_uuid' => $operation->uuid,
                        'sha256' => $archive->sha256,
                        'size_bytes' => $archive->size_bytes,
                        'entry_count' => $archive->entry_count,
                        'reused_archive' => $reusedArchive,
                    ],
                    $requestId,
                    $sourceIp,
                    $operation,
                );

                return new StoredArchiveDeployment($archive, $deployment, $operation, $reusedArchive);
            });

            return $result;
        } catch (Throwable $exception) {
            if ($keepStoredFile) {
                Storage::disk($disk)->delete($path);
            } elseif (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }

            throw $exception;
        }
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        return basename(str_replace('\\', '/', $file->getClientOriginalName()));
    }
}
