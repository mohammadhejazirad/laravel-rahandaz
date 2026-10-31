<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\DockerController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\GitConnectionController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\MirrorController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PanelPortController;
use App\Http\Controllers\PhpServerController;
use App\Http\Controllers\ProjectArchiveController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectLaravelController;
use App\Http\Controllers\ProjectLaunchController;
use App\Http\Controllers\PwaController;
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
    Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
    Route::get('/service-worker.js', [PwaController::class, 'worker'])->name('pwa.worker');

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
            Route::patch('/setup', [SetupController::class, 'save'])->middleware('throttle:30,1')->name('setup.save');
            Route::post('/setup/apply', [SetupController::class, 'apply'])->middleware('throttle:5,1')->name('setup.apply');

            Route::middleware(['setup.complete', 'panel.section'])->group(function () {
                Route::get('/dashboard', DashboardController::class)->name('dashboard');
                Route::get('/servers', [ServerController::class, 'index'])->name('servers.index');
                Route::get('/servers/php', [PhpServerController::class, 'show'])->name('servers.php');
                Route::put('/servers/php', [PhpServerController::class, 'update'])->middleware('throttle:10,1')->name('servers.php.update');
                Route::get('/servers/{localHost}', [ServerController::class, 'show'])->name('servers.show');
                Route::post('/servers/{localHost}/services', [ServerController::class, 'service'])->middleware('throttle:20,1')->name('servers.services');
                Route::get('/docker', [DockerController::class, 'index'])->name('docker.index');
                Route::get('/docker/apps', [DockerController::class, 'apps'])->name('docker.apps');
                Route::get('/docker/compose', [DockerController::class, 'compose'])->name('docker.compose');
                Route::post('/docker/stacks', [DockerController::class, 'store'])->middleware('throttle:10,1')->name('docker.stacks.store');
                Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
                Route::get('/catalog/installed', [CatalogController::class, 'installed'])->name('catalog.installed');
                Route::post('/catalog/software/install', [CatalogController::class, 'installSoftware'])->middleware('throttle:5,1')->name('catalog.software.install');
                Route::post('/catalog/software/upgrade', [CatalogController::class, 'upgradeSoftware'])->middleware('throttle:5,1')->name('catalog.software.upgrade');
                Route::post('/catalog/chrome/{localHost}/install', [CatalogController::class, 'installChrome'])->middleware('throttle:5,1')->name('catalog.chrome.install');
                Route::get('/mirrors', [MirrorController::class, 'index'])->name('mirrors.index');
                Route::post('/git/github/register', [GitConnectionController::class, 'manifestStart'])->middleware('throttle:5,1')->name('git.manifest.start');
                Route::get('/git/github/registered', [GitConnectionController::class, 'manifestCallback'])->middleware('throttle:10,1')->name('git.manifest.callback');
                Route::get('/git', [GitConnectionController::class, 'index'])->name('git.index');
                Route::put('/git/providers/{provider}', [GitConnectionController::class, 'settings'])->where('provider', 'github|gitlab|gitlabSelf')->middleware('throttle:10,1')->name('git.settings');
                Route::post('/git/authorize/{provider}', [GitConnectionController::class, 'start'])->where('provider', 'github|gitlab|gitlabSelf')->middleware('throttle:10,1')->name('git.start');
                Route::get('/git/callback/{provider}', [GitConnectionController::class, 'callback'])->where('provider', 'github|gitlab|gitlabSelf')->middleware('throttle:20,1')->name('git.callback');
                Route::post('/git/providers/{provider}/test', [GitConnectionController::class, 'testProvider'])->where('provider', 'github|gitlab|gitlabSelf')->middleware('throttle:5,1')->name('git.provider-test');
                Route::post('/git/providers/{provider}/manual', [GitConnectionController::class, 'manual'])->where('provider', 'github|gitlab|gitlabSelf')->middleware('throttle:5,1')->name('git.manual');
                Route::post('/git/connections/{connection}/test', [GitConnectionController::class, 'testConnection'])->middleware('throttle:5,1')->name('git.test');
                Route::get('/git/connections/{connection}', [GitConnectionController::class, 'show'])->name('git.show');
                Route::post('/git/connections/{connection}/sync', [GitConnectionController::class, 'sync'])->middleware('throttle:5,1')->name('git.sync');
                Route::put('/git/connections/{connection}/repositories', [GitConnectionController::class, 'select'])->middleware('throttle:20,1')->name('git.select');
                Route::delete('/git/connections/{connection}', [GitConnectionController::class, 'disconnect'])->middleware('throttle:5,1')->name('git.disconnect');
                Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
                Route::get('/projects/new', [ProjectLaunchController::class, 'create'])->name('projects.create');
                Route::post('/projects/sources/{repository}/branches', [ProjectLaunchController::class, 'branches'])->middleware('throttle:20,1')->name('projects.branches');
                Route::post('/projects', [ProjectLaunchController::class, 'store'])->middleware('throttle:10,1')->name('projects.store');
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
                Route::post('/projects/{project}/docker', [ProjectController::class, 'docker'])->middleware('throttle:20,1')->name('projects.docker');
                Route::put('/projects/{project}/laravel', [ProjectLaravelController::class, 'update'])->middleware('throttle:20,1')->name('projects.laravel');
                Route::get('/access', [AccessController::class, 'index'])->name('access.index');
                Route::post('/access/workspaces', [AccessController::class, 'storeWorkspace'])->middleware('throttle:10,1')->name('access.workspaces.store');
                Route::post('/access/workspaces/{workspace}/current', [AccessController::class, 'switchWorkspace'])->middleware('throttle:20,1')->name('access.switch');
                Route::post('/access/users', [AccessController::class, 'storeUser'])->middleware('throttle:10,1')->name('access.users.store');
                Route::put('/access/memberships/{membership}', [AccessController::class, 'updateSections'])->middleware('throttle:20,1')->name('access.sections');
                Route::put('/access/memberships/{membership}/role', [AccessController::class, 'updateRole'])->middleware('throttle:20,1')->name('access.role');
                Route::put('/access/memberships/{membership}/password', [AccessController::class, 'updatePassword'])->middleware('throttle:10,1')->name('access.password');
                Route::put('/access/memberships/{membership}/status', [AccessController::class, 'updateStatus'])->middleware('throttle:10,1')->name('access.status');
                Route::post('/projects/{project}/environments/{environment}/source-archives', [ProjectArchiveController::class, 'store'])
                    ->middleware('throttle:5,1')
                    ->name('projects.source-archives.store');
                Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
                Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
                Route::post('/jobs/{job}/cancel', [JobController::class, 'cancel'])->middleware('throttle:20,1')->name('jobs.cancel');
                Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
                Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
                Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
                Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
                Route::post('/settings/panel-port', [PanelPortController::class, 'update'])->middleware('throttle:3,1')->name('settings.panel-port');
                Route::get('/settings/panel-port/status', [PanelPortController::class, 'status'])->name('settings.panel-port.status');
                Route::get('/settings/profile', [SettingsController::class, 'profile'])->name('settings.profile');
                Route::get('/settings/updates', [SystemUpdateController::class, 'show'])->name('settings.updates');
                Route::post('/settings/updates/check', [SystemUpdateController::class, 'check'])
                    ->middleware('throttle:6,1')
                    ->name('settings.updates.check');
            });
        });
    });
});
