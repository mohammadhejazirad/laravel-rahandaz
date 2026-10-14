<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\MirrorController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectArchiveController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SystemUpdateController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\TutorialController;
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
            Route::get('/tutorials', [TutorialController::class, 'index'])->name('tutorials.index');
            Route::get('/tutorials/{slug}', [TutorialController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('tutorials.show');
            Route::put('/tutorials/{slug}/bookmark', [TutorialController::class, 'bookmark'])->where('slug', '[a-z0-9-]+')->middleware('throttle:60,1')->name('tutorials.bookmark');
            Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');

            Route::middleware('setup.complete')->group(function () {
                Route::get('/dashboard', DashboardController::class)->name('dashboard');
                Route::get('/servers', [ServerController::class, 'index'])->name('servers.index');
                Route::get('/servers/{localHost}', [ServerController::class, 'show'])->name('servers.show');
                Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
                Route::get('/mirrors', [MirrorController::class, 'index'])->name('mirrors.index');
                Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
                Route::get('/deployments', [DeploymentController::class, 'index'])->name('deployments.index');
                Route::post('/deployments', [DeploymentController::class, 'store'])->middleware('throttle:10,1')->name('deployments.store');
                Route::get('/deployments/{deployment}', [DeploymentController::class, 'show'])->name('deployments.show');
                Route::post('/deployments/{deployment}/{action}', [DeploymentController::class, 'action'])->where('action', 'cancel|retry')->middleware('throttle:10,1')->name('deployments.action');
                Route::get('/domains', [DomainController::class, 'index'])->name('domains.index');
                Route::post('/domains', [DomainController::class, 'store'])->middleware('throttle:10,1')->name('domains.store');
                Route::get('/domains/{domain}', [DomainController::class, 'show'])->name('domains.show');
                Route::post('/domains/{domain}/certificate', [DomainController::class, 'certificate'])->middleware('throttle:5,1')->name('domains.certificate');
                Route::get('/databases', [DatabaseController::class, 'index'])->name('databases.index');
                Route::post('/databases', [DatabaseController::class, 'store'])->middleware('throttle:5,1')->name('databases.store');
                Route::get('/databases/{database}', [DatabaseController::class, 'show'])->name('databases.show');
                Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
                Route::post('/backups', [BackupController::class, 'store'])->middleware('throttle:5,1')->name('backups.store');
                Route::get('/backups/schedules', [BackupController::class, 'schedules'])->name('backups.schedules');
                Route::get('/backups/{backup}', [BackupController::class, 'show'])->name('backups.show');
                Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
                Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
                Route::post('/projects/{project}/environments/{environment}/source-archives', [ProjectArchiveController::class, 'store'])
                    ->middleware('throttle:5,1')
                    ->name('projects.source-archives.store');
                Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
                Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
                Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
                Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
                Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
                Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
                Route::get('/settings/profile', [SettingsController::class, 'profile'])->name('settings.profile');
                Route::get('/settings/updates', [SystemUpdateController::class, 'show'])->name('settings.updates');
                Route::post('/settings/updates/check', [SystemUpdateController::class, 'check'])
                    ->middleware('throttle:6,1')
                    ->name('settings.updates.check');
            });
        });
    });
});
