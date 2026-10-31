<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsurePanelSection;
use App\Http\Middleware\EnsurePasswordIsCurrent;
use App\Http\Middleware\EnsureSetupComplete;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ThrottleByRoute;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        $middleware->web(append: [
            AssignRequestId::class,
            AuthenticateSession::class,
            EnsureAccountActive::class,
            SecurityHeaders::class,
            HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'password.current' => EnsurePasswordIsCurrent::class,
            'setup.complete' => EnsureSetupComplete::class,
            'panel.section' => EnsurePanelSection::class,
            'throttle' => ThrottleByRoute::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['client_secret', 'access_token', 'refresh_token']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            if (! in_array($status, [403, 404, 419, 500, 503], true)) {
                return $response;
            }

            if ($request->expectsJson() || $response->isRedirection()) {
                return $response;
            }

            if (! $request->header('X-Inertia')) {
                return response()->view('errors.show', [
                    'status' => $status,
                ], $status);
            }

            return Inertia::render('errors/show', [
                'status' => $status,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
