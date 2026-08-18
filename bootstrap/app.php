<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'pharmacy.pending' => \App\Http\Middleware\PharmacyPendingMiddleware::class,
        ]);

        // Prevent browser from caching pages so back button after logout won't show stale content
        $middleware->web(append: [
            \App\Http\Middleware\NoCacheHeaders::class,
        ]);
    })
    
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
