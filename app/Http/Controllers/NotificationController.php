<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\CurrentWorkspace;
use App\Http\PaginatorPayload;
use App\Models\PanelNotification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = CurrentWorkspace::get($request);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $base = '/'.trim((string) config('rahandaz.base_path'), '/');

        $notifications = PanelNotification::query()
            ->where('workspace_id', $workspace->id)
            ->where('recipient_id', $user->id)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('notifications/index', [
            'notifications' => [
                'data' => $notifications->getCollection()->map(function (PanelNotification $notification) use ($base): array {
                    $path = $notification->action_path;
                    $safePath = is_string($path) && str_starts_with($path, $base) && ! str_contains($path, '://')
                        ? $path
                        : null;

                    return [
                        'uuid' => $notification->uuid,
                        'level' => $notification->level->value,
                        'title' => $notification->title,
                        'body' => $notification->body,
                        'read_at' => $notification->read_at?->utc()->toIso8601ZuluString(),
                        'action_path' => $safePath,
                        'created_at' => $notification->created_at?->utc()->toIso8601ZuluString(),
                    ];
                })->all(),
                'meta' => PaginatorPayload::meta($notifications),
            ],
        ]);
    }

    public function read(Request $request, PanelNotification $notification): RedirectResponse
    {
        $this->authorizeRecipient($request, $notification);
        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return back()->with('status', 'اعلان به‌عنوان خوانده‌شده علامت خورد.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $workspace = CurrentWorkspace::get($request);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        PanelNotification::query()
            ->where('workspace_id', $workspace->id)
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'اعلان‌های خوانده‌نشده علامت خوردند.');
    }

    private function authorizeRecipient(Request $request, PanelNotification $notification): void
    {
        $workspace = CurrentWorkspace::get($request);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless((string) $notification->workspace_id === (string) $workspace->id, 404);
        abort_unless((string) $notification->recipient_id === (string) $user->id, 403);
    }
}
