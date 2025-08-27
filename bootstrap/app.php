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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.project' => \App\Http\Middleware\AuthenticateProject::class,
            'rate_limit' => \App\Http\Middleware\RateLimitMiddleware::class,
            'rate_limit_requests' => \App\Http\Middleware\RateLimitRequests::class,
            'production_security' => \App\Http\Middleware\ProductionSecurityMiddleware::class,
            'admin_auth' => \App\Http\Middleware\AdminAuthMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
