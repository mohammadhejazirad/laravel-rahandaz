<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Jobs\Enums\JobStatus;
use App\Http\CurrentWorkspace;
use App\Infrastructure\Updates\UpdateStatusService;
use App\Models\LocalHost;
use App\Models\MetricSample;
use App\Models\OperationJob;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, UpdateStatusService $updates): Response
    {
        $workspace = CurrentWorkspace::get($request);

        $counts = OperationJob::query()
            ->where('workspace_id', $workspace->id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function (OperationJob $row): array {
                $status = $row->status instanceof JobStatus ? $row->status->value : (string) $row->status;

                return [$status => (int) $row->aggregate];
            });

        return Inertia::render('dashboard/index', [
            'workspace' => [
                'uuid' => $workspace->uuid,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
            ],
            'execution_mode' => (string) config('rahandaz.execution_mode'),
            'operations' => [
                'queued' => (int) ($counts['queued'] ?? 0),
                'running' => (int) ($counts['running'] ?? 0),
                'failed' => (int) ($counts['failed'] ?? 0),
            ],
            'inventory' => [
                'projects' => Project::query()->forWorkspace($workspace->id)->count(),
                'servers' => LocalHost::query()->forWorkspace($workspace->id)->count(),
                'metric_samples' => MetricSample::query()
                    ->whereIn('local_host_id', LocalHost::query()->forWorkspace($workspace->id)->select('id'))
                    ->count(),
            ],
            'release' => $updates->snapshot(),
        ]);
    }
}
