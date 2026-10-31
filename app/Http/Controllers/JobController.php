<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Access\OperationAccess;
use App\Domain\Catalog\SoftwareCapability;
use App\Domain\Jobs\CancelDecision;
use App\Domain\Jobs\CancelOperation;
use App\Domain\Jobs\Enums\JobStatus;
use App\Http\CurrentWorkspace;
use App\Http\PaginatorPayload;
use App\Models\OperationJob;
use App\Models\OperationJobLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = CurrentWorkspace::get($request);

        $filters = $request->validate(['status' => ['nullable', Rule::enum(JobStatus::class)]]);
        $status = $filters['status'] ?? '';
        $jobs = app(OperationAccess::class)->visibleQuery($request->user(), $workspace->id)
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('jobs/index', [
            'filters' => ['status' => $status],
            'jobs' => [
                'data' => $jobs->getCollection()->map(fn (OperationJob $job): array => $this->summary($job))->all(),
                'meta' => PaginatorPayload::meta($jobs),
            ],
        ]);
    }

    public function show(Request $request, OperationJob $job): Response
    {
        $this->authorize('view', $job);
        $workspace = CurrentWorkspace::get($request);
        abort_unless((string) $job->workspace_id === (string) $workspace->id, 404);

        $logs = OperationJobLog::query()
            ->where('operation_job_id', $job->id)
            ->orderBy('sequence')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('jobs/show', [
            'job' => $this->summary($job),
            'software' => in_array($job->type, SoftwareCapability::JOB_TYPES, true) ? app(SoftwareCapability::class)->payload($job) : null,
            'logs' => [
                'data' => $logs->getCollection()->map(fn (OperationJobLog $log): array => [
                    'sequence' => $log->sequence,
                    'level' => $log->level,
                    'step_key' => $log->step_key,
                    'message' => $log->message,
                    'recorded_at' => $log->recorded_at?->utc()->toIso8601ZuluString(),
                ])->all(),
                'meta' => PaginatorPayload::meta($logs),
            ],
            'cancel_available' => $this->cancelAvailable($job),
        ]);
    }

    public function cancel(Request $request, OperationJob $job): RedirectResponse
    {
        $this->authorize('view', $job);
        $workspace = CurrentWorkspace::get($request);
        abort_unless((string) $job->workspace_id === (string) $workspace->id, 404);
        $mode = app(CancelOperation::class)->request($job, $request->user(), $request->ip());

        return back()->with('status', $mode === 'finish'
            ? 'عملیات هنوز شروع‌نشده لغو شد. فرمانی روی میزبان از این درخواست اجرا نشد.'
            : 'درخواست لغو ثبت شد. توقف فرایندی که شاید شروع شده باشد هنوز اثبات نشده است.');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(OperationJob $job): array
    {
        return [
            'uuid' => $job->uuid,
            'type' => $job->type,
            'status' => $job->status->value,
            'attempt' => $job->attempt,
            'cancel_requested' => (bool) $job->cancel_requested,
            'error_code' => $job->error_code,
            'created_at' => $job->created_at?->utc()->toIso8601ZuluString(),
            'finished_at' => $job->finished_at?->utc()->toIso8601ZuluString(),
        ];
    }

    private function cancelAvailable(OperationJob $job): bool
    {
        $mode = app(CancelDecision::class)->mode($job->status);

        return $mode === 'finish' || ($mode === 'flag' && ! $job->cancel_requested);
    }
}
