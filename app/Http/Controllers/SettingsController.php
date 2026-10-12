<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\CurrentWorkspace;
use App\Models\User;
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
        ]);
    }
}
