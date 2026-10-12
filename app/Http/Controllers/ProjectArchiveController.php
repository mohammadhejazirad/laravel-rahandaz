<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Projects\Exceptions\ArchiveRejected;
use App\Domain\Projects\Exceptions\DeploymentLockConflict;
use App\Domain\Projects\ProjectArchiveUploader;
use App\Http\CurrentWorkspace;
use App\Http\Requests\ProjectArchiveUploadRequest;
use App\Models\Environment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ProjectArchiveController extends Controller
{
    public function store(
        ProjectArchiveUploadRequest $request,
        Project $project,
        Environment $environment,
        ProjectArchiveUploader $uploader,
    ): RedirectResponse {
        $workspace = CurrentWorkspace::get($request);
        $this->authorize('deploy', $project);
        abort_unless((string) $project->workspace_id === (string) $workspace->id, 404);
        abort_unless((string) $environment->project_id === (string) $project->id, 404);
        abort_unless((string) $environment->workspace_id === (string) $workspace->id, 404);

        $project->loadMissing('workspace');
        $file = $request->file('archive');
        $actor = $request->user();

        if ($file === null || ! $actor instanceof User) {
            throw ValidationException::withMessages(['archive' => 'یک فایل ZIP انتخاب کنید.']);
        }

        try {
            $result = $uploader->storeAndQueue(
                $file,
                $project,
                $environment,
                $actor,
                $request->attributes->get('request_id'),
                $request->ip(),
            );
        } catch (ArchiveRejected $exception) {
            throw ValidationException::withMessages(['archive' => $exception->getMessage()]);
        } catch (DeploymentLockConflict) {
            throw ValidationException::withMessages([
                'archive' => 'برای این محیط یک استقرار تغییردهنده در حال انتظار یا اجرا است.',
            ]);
        }

        $message = $result->reusedArchive
            ? 'این آرشیو قبلاً بررسی شده بود؛ یک درخواست استقرار دستی تازه برای آن ثبت شد.'
            : 'فایل ZIP بررسی و در فضای خصوصی ذخیره شد و درخواست استقرار دستی ثبت شد.';

        return to_route('projects.show', $project)
            ->with('info', $message.' اجرای میزبان هنوز موفق اعلام نشده و وضعیت عملیات باید جدا بررسی شود.');
    }
}
