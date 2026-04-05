<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureJsonAccept;
use App\Http\Middleware\IdempotencyKey;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetCacheHeaders;
use App\Http\Middleware\UpdateUserLastSeen;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->append(SecurityHeaders::class);

        $middleware->api(append: [
            EnsureJsonAccept::class,
            UpdateUserLastSeen::class,
        ]);

        $middleware->alias([
            'permission' => CheckPermission::class,
            'cache.headers' => SetCacheHeaders::class,
            'idempotency' => IdempotencyKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function ($request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        $exceptions->render(function (NotFoundHttpException|ModelNotFoundException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Resource not found.',
                ], 404);
            }
        });

        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $jsonApiErrors = [];
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $jsonApiErrors[] = [
                            'status' => '422',
                            'title' => 'Validation Error',
                            'detail' => $message,
                            'source' => ['pointer' => "/data/attributes/{$field}"],
                        ];
                    }
                }

                return response()->json(['errors' => $jsonApiErrors], 422);
            }
        });

        $exceptions->render(function (HttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'An error occurred.',
                ], $e->getStatusCode());
            }
        });
    })->create();
