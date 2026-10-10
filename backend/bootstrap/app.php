<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 🔑 Sanctum SPA stateful auth
        $middleware->statefulApi();

        // 🔑 Disable redirect for unauthenticated API requests
        $middleware->redirectGuestsTo(fn () => null);
        
        // 🔑 Disable redirect for unverified email
        $middleware->redirectUsersTo(fn () => null);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 🔑 Return JSON for all API exceptions
        $exceptions->render(function (Throwable $e, $request) {
            // Only handle API requests
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $status = 500;
            $message = 'Something went wrong';
            $errors = [];

            if ($e instanceof ValidationException) {
                $status = 422;
                $message = 'Validation failed';
                $errors = $e->errors();
            } elseif ($e instanceof AuthenticationException) {
                $status = 401;
                $message = 'Unauthenticated';
            } elseif ($e instanceof AuthorizationException) {
                $status = 403;
                $message = 'Forbidden';
            } elseif ($e instanceof ModelNotFoundException) {
                $status = 404;
                $message = 'Resource not found';
            } elseif ($e instanceof NotFoundHttpException) {
                $status = 404;
                $message = 'Route not found';
            } elseif ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $message = $e->getMessage() ?: $message;
            } else {
                $message = config('app.debug') ? $e->getMessage() : $message;
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors'  => $errors ?: null,
            ], $status);
        });
    })->create();