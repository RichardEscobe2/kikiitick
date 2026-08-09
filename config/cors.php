<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Aquí defines qué orígenes (dominios), métodos y encabezados pueden
    | realizar peticiones a tu backend Laravel desde el navegador.
    |
    */

    // Rutas de Laravel expuestas a CORS (API, ruta CSRF de Sanctum, y login/logout
    // por si alguna vez se exponen fuera del prefijo /api/*)
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    // Permite los métodos HTTP estándar (GET, POST, PUT, DELETE, OPTIONS)
    'allowed_methods' => ['*'],

    // 🛡️ DOMINIOS PERMITIDOS
    // Solo estas URLs tendrán permiso para consumir tu API.
    'allowed_origins' => array_filter([
        'http://localhost:5173',      // Frontend Vue + Vite en desarrollo
        'http://127.0.0.1:5173',      // Alternativa local
        'http://localhost:8000',      // App servida directamente por Laravel (php artisan serve)
        'http://127.0.0.1:8000',      // Alternativa local de lo anterior — distinto origin para el navegador aunque sea la misma máquina
        'https://kikiitick.com.mx',    // Tu dominio real en producción
        // 🛡️ Lee APP_URL en vez de hardcodear el dominio de ngrok: la URL rota cada vez
        // que se reinicia el túnel (plan free), así que basta con actualizar APP_URL en
        // .env para que CORS la reconozca automáticamente, sin tocar este archivo.
        env('APP_URL'),
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // 🔒 VITAL PARA SANCTUM: Permite el envío de Cookies y credenciales HTTP
    'supports_credentials' => true,

];