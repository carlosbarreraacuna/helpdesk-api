<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Symfony\Component\HttpFoundation\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The refresh_token cookie is already httpOnly (JS can't read it) and contains
        // high-entropy random bytes, so Laravel-level encryption is unnecessary overhead.
        $middleware->encryptCookies(except: ['refresh_token']);

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

        // Railway termina TLS y hace proxy a este contenedor — confiamos en el proxy
        // para leer la IP real del cliente desde X-Forwarded-For (si no, $request->ip()
        // devuelve la IP interna del proxy de Railway en vez de la del usuario).
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);

        /*
        |--------------------------------------------------------------------------
        | Middleware aliases
        |--------------------------------------------------------------------------
        */

        // Security headers en todas las respuestas
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'verified'          => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'permission'        => \App\Http\Middleware\CheckPermission::class,
            'password.expired'  => \App\Http\Middleware\CheckPasswordExpired::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
