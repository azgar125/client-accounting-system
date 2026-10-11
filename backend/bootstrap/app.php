<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ✅ CSRF exemption যোগ করুন এখানে
        $middleware->validateCsrfTokens(except: [
            'api/auth/login',
            'api/auth/register',
            'api/auth/logout',
            'sanctum/csrf-cookie',
            'api/*',   // ⚠️ চাইলে সব API exempt করতে পারেন
        ]);

        // আপনার অন্যান্য middleware config এখানে থাকবে
        // $middleware->api(prepend: [...]);
        // $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();