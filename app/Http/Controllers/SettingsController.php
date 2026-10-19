<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Settings\PanelListener;
use App\Http\CurrentWorkspace;
use App\Models\OperationJob;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function profile(Request $request): Response
    {
        $workspace = CurrentWorkspace::get($request);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $user->loadMissing('profile');

        return Inertia::render('settings/profile', [
            'profile' => [
                'username' => $user->username,
                'display_name' => $user->profile?->display_name,
                'timezone' => $user->timezone,
                'locale' => $user->locale,
                'theme' => $user->theme->value,
            ],
            'workspace' => [
                'uuid' => $workspace->uuid,
                'name' => $workspace->name,
            ],
            'mfa_available' => false,
            'panel_port' => $this->panelPort($request, $workspace),
        ]);
    }

    private function panelPort(Request $request, Workspace $workspace): array
    {
        $listener = app(PanelListener::class);
        $state = $listener->state();
        $owner = $listener->owner($request->user(), $workspace);
        $job = $owner ? OperationJob::query()->where('workspace_id', $workspace->id)->where('type', 'panel.port.update')->latest('id')->first() : null;

        return [
            'available' => $state !== null,
            'can_change' => $owner && $state !== null,
            'port' => $state['port'] ?? null,
            'origin' => $state !== null ? $listener->origin($state) : null,
            'job' => $job !== null ? ['uuid' => $job->uuid, 'status' => $job->status->value, 'error_code' => $job->error_code,
                'target_origin' => $state !== null ? $listener->origin($state, (int) $job->plan['input']['port']) : null] : null,
        ];
    }
}
