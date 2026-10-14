<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Updates\UpdateChecker;
use App\Infrastructure\Updates\UpdateStatusService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemUpdateController extends Controller
{
    public function show(Request $request, UpdateStatusService $status): Response
    {
        abort_unless($request->user() instanceof User, 403);

        return Inertia::render('settings/updates', [
            'release' => $status->snapshot(),
        ]);
    }

    public function check(Request $request, UpdateChecker $checker): RedirectResponse
    {
        abort_unless($request->user() instanceof User, 403);
        $checker->run();

        return redirect()
            ->route('settings.updates')
            ->with('info', 'بررسی نسخه انجام شد. اگر منبع نسخه در دسترس نباشد، ورود و پنل متوقف نمی‌شوند.');
    }
}
