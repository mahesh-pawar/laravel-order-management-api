<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // API has no login page: never redirect guests, just throw 401
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            // Only reshape API errors; let web errors render normally
            if (!$request->is('api/*')) {
                return null;
            }

            [$status, $message] = match (true) {
                $e instanceof ValidationException     => [422, 'Validation failed.'],
                $e instanceof AuthenticationException => [401, 'Unauthenticated.'],
                $e instanceof NotFoundHttpException   => [404, 'Resource not found.'],
                $e instanceof HttpExceptionInterface  => [$e->getStatusCode(), $e->getMessage() ?: 'Request failed.'],
                default => [500, config('app.debug') ? $e->getMessage() : 'Server error.'],
            };

            $body = ['success' => false, 'message' => $message];

            if ($e instanceof ValidationException) {
                $body['errors'] = $e->errors();
            }

            return response()->json($body, $status);
        });
    })->create();
