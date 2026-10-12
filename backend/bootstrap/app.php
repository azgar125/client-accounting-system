<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {

        /*
        |--------------------------------------------------------------------------
        | Sanctum SPA Authentication
        |--------------------------------------------------------------------------
        */

        $middleware->statefulApi();

        /*
        |--------------------------------------------------------------------------
        | Start Session for API
        |--------------------------------------------------------------------------
        |
        | Your AuthController uses:
        |
        | $request->session()
        |
        | Therefore the API requests must have StartSession middleware.
        |
        */

        $middleware->appendToGroup('api', [
            StartSession::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | CSRF
        |--------------------------------------------------------------------------
        |
        | API routes are excluded because the React frontend
        | communicates with Laravel API.
        |
        */

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();