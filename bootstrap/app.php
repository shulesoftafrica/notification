<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Add API logging middleware to all API routes
        $middleware->api(prepend: [
            \App\Http\Middleware\ApiLoggingMiddleware::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'admin.auth' => \App\Http\Middleware\AdminAuthMiddleware::class,
            'api.auth' => \App\Http\Middleware\ApiAuthMiddleware::class,
            'rate.limit' => \App\Http\Middleware\RateLimitRequests::class,
            'production.security' => \App\Http\Middleware\ProductionSecurityMiddleware::class,
            'api.logging' => \App\Http\Middleware\ApiLoggingMiddleware::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Log all exceptions with detailed context
        $exceptions->reportable(function (\Throwable $e) {
            Log::channel('errors')->error('Exception occurred', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'url' => request()->fullUrl(),
                'method' => request()->method(),
                'input' => request()->all(),
                'user_agent' => request()->userAgent(),
                'ip' => request()->ip(),
            ]);
        });
    })->create();
