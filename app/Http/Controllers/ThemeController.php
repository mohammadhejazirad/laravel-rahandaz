<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Accounts\Enums\Theme;
use App\Http\Requests\ThemeUpdateRequest;
use Illuminate\Http\RedirectResponse;

class ThemeController extends Controller
{
    public function update(ThemeUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $user->forceFill([
            'theme' => Theme::from($request->string('theme')->toString()),
        ])->save();

        return back();
    }
}
