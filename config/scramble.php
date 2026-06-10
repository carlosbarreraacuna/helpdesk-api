<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    /*
     * Your API path. By default, all routes starting with this path will be documented.
     * Specify the path using the relative path from your app root.
     */
    'api_path' => 'api',

    /*
     * Your API domain. By default, app domain is used. This is useful when you have
     * subdomains in your application.
     */
    'api_domain' => null,

    /*
     * Metadata for the generated OpenAPI document.
     */
    'info' => [
        'title'       => env('APP_NAME', 'CARDIQUE Helpdesk') . ' — API',
        'description' => 'Documentación de la API REST del Sistema de Mesa de Ayuda CARDIQUE. ' .
                         'Autenticación mediante Bearer token (Laravel Sanctum). ' .
                         'Los tokens expiran en 60 minutos; use el endpoint /api/auth/refresh para renovarlos.',
        'version'     => '1.0.0',
    ],

    /*
     * Servers information for the generated OpenAPI document.
     */
    'servers' => null,

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],
];
