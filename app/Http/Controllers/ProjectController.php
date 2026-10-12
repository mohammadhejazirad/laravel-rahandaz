<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $project->loadMissing('workspace');

        return response()->json([
            'uuid' => $project->uuid,
            'name' => $project->name,
            'workspace_uuid' => $project->workspace?->uuid,
        ]);
    }
}
