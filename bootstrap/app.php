<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register middleware aliases
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'siswa' => \App\Http\Middleware\SiswaMiddleware::class,
            'authenticated' => \App\Http\Middleware\AuthenticatedMiddleware::class,
            'guest.custom' => \App\Http\Middleware\GuestMiddleware::class,
        ]);

        // Middleware priority
        $middleware->priority([
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle exceptions khusus untuk Livewire
        $exceptions->render(function (Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->hasHeader('X-Livewire')) {
                return response()->json([
                    'error' => 'Unauthenticated',
                    'redirect' => route('login')
                ], 401);
            }
            return null;
        });
    })->create();
