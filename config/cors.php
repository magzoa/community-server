<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS — Módulo Comunidad
    |--------------------------------------------------------------------------
    | Autenticación por tokens Bearer: no se usan cookies, por lo que
    | supports_credentials queda en false. Se permite el origen del cliente
    | Vite (http://localhost:5173) sobre las rutas de API.
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        'http://localhost:5173',
        'http://localhost:5174',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
        // Origen del cliente en producción (define CORS_ALLOWED_ORIGIN en el .env)
        env('CORS_ALLOWED_ORIGIN'),
    ]),

    // Cubre cualquier puerto de Vite en desarrollo (localhost / 127.0.0.1)
    'allowed_origins_patterns' => [
        '/^http:\/\/(localhost|127\.0\.0\.1):\d+$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
