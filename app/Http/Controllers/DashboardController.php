<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Jobs\Enums\JobStatus;
use App\Models\OperationJob;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $user = request()->user();
        abort_if($user === null, 403);

        $workspace = $user->currentWorkspace();
        abort_if($workspace === null, 403);
        $this->authorize('view', $workspace);

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
            'modules' => [
                'projects' => 'not_installed',
                'servers' => 'not_installed',
            ],
        ]);
    }
}
