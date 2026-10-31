<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\PanelSections;
use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Docker\DockerInstallation;
use App\Models\PanelNotification;
use App\Support\EnumLabelCatalog;
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
                    'uuid' => $user->uuid,
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
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'execution' => [
                'mode' => (string) config('rahandaz.execution_mode'),
            ],
            'app' => [
                'name' => 'راه‌انداز',
                'base_path' => (string) config('rahandaz.base_path'),
            ],
            'docker' => [
                'installed' => $user !== null && app(DockerInstallation::class)->present(),
            ],
            'access' => [
                'owner' => $membership?->role === MembershipRole::Owner,
                'sections' => $membership === null || $membership->role === MembershipRole::Owner || $membership->sections === null
                    ? null
                    : array_values(array_intersect($membership->sections, array_keys(PanelSections::all()))),
            ],
            'notifications' => [
                'unread' => $user === null || $membership === null ? 0 : PanelNotification::query()
                    ->where('workspace_id', $membership->workspace_id)
                    ->where('recipient_id', $user->id)
                    ->whereNull('read_at')
                    ->count(),
            ],
            'enum_labels' => EnumLabelCatalog::all(),
        ];
    }
}
