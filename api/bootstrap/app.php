<?php

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($isApi);

        // Every API error uses the same envelope as successful responses.
        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => ApiResponse::error($e->getMessage(), 422, $e->errors()),
                $e instanceof AuthenticationException => ApiResponse::error('Unauthenticated.', 401),
                $e instanceof AuthorizationException => ApiResponse::error('Forbidden.', 403),
                // Same response for "missing" and "not yours" so IDs can't be probed across tenants.
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => ApiResponse::error('Not found.', 404),
                $e instanceof HttpExceptionInterface => ApiResponse::error($e->getMessage() ?: 'Error.', $e->getStatusCode()),
                default => config('app.debug') ? null : ApiResponse::error('Server error.', 500),
            };
        });
    })->create();
