<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        /*
        |--------------------------------------------------------------------------
        | API Middleware
        |--------------------------------------------------------------------------
        | Se agrega el middleware de CORS OFICIAL de Laravel
        | para que responda correctamente a OPTIONS (preflight)
        | y aplique config/cors.php
        */

        $middleware->api(prepend: [
            HandleCors::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Middleware aliases
        |--------------------------------------------------------------------------
        */

        $middleware->alias([
            'verified'   => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
