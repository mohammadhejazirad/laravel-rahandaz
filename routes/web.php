<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $login = rtrim((string) config('rahandaz.base_path'), '/').'/login';

    return response("Not found. Panel login: {$login}\n", 404)
        ->header('Content-Type', 'text/plain; charset=UTF-8');
});

Route::prefix(trim((string) config('rahandaz.base_path'), '/'))->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'))->name('home');

    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
        Route::patch('/theme', [ThemeController::class, 'update'])->name('theme.update');
        Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
        Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

        Route::middleware('password.current')->group(function () {
            Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');

            Route::middleware('setup.complete')->group(function () {
                Route::get('/dashboard', DashboardController::class)->name('dashboard');
                Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
            });
        });
    });
});
