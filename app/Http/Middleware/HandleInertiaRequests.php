<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user !== null) {
            $user->loadMissing('profile', 'memberships');
        }

        $membership = $user?->currentMembership();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'username' => $user->username,
                    'display_name' => $user->profile?->display_name,
                    'role' => $membership?->role?->value,
                    'theme' => $user->theme->value,
                    'locale' => $user->locale,
                    'timezone' => $user->timezone,
                    'force_password_change' => (bool) $user->profile?->force_password_change,
                ],
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'execution' => [
                'mode' => (string) config('rahandaz.execution_mode'),
            ],
            'app' => [
                'name' => 'راه‌انداز',
                'base_path' => (string) config('rahandaz.base_path'),
            ],
        ];
    }
}
