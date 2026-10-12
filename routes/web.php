<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\MirrorController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\SettingsController;
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
                Route::get('/servers', [ServerController::class, 'index'])->name('servers.index');
                Route::get('/servers/{localHost}', [ServerController::class, 'show'])->name('servers.show');
                Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
                Route::get('/mirrors', [MirrorController::class, 'index'])->name('mirrors.index');
                Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
                Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
                Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
                Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
                Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
                Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
                Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
                Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
                Route::get('/settings/profile', [SettingsController::class, 'profile'])->name('settings.profile');
            });
        });
    });
});
