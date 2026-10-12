<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function edit(): Response|RedirectResponse
    {
        $user = request()->user();
        $user?->loadMissing('profile');

        if ($user?->profile?->force_password_change !== true) {
            return redirect()->route('setup.show');
        }

        return Inertia::render('auth/password');
    }

    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $user->forceFill([
            'password' => $request->string('password')->toString(),
        ])->save();

        $user->profile?->forceFill([
            'force_password_change' => false,
        ])->save();

        Auth::logoutOtherDevices($request->string('password')->toString());
        $request->session()->regenerate();

        return redirect()
            ->route('setup.show')
            ->with('status', 'گذرواژه تغییر کرد.');
    }
}
