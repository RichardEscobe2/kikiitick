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

    // Rutas de Laravel expuestas a CORS (API y ruta CSRF de Sanctum)
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Permite los métodos HTTP estándar (GET, POST, PUT, DELETE, OPTIONS)
    'allowed_methods' => ['*'],

    // 🛡️ DOMINIOS PERMITIDOS
    // Solo estas URLs tendrán permiso para consumir tu API.
    'allowed_origins' => [
        'http://localhost:5173',      // Frontend Vue + Vite en desarrollo
        'http://127.0.0.1:5173',      // Alternativa local
        'https://kikiitick.com.mx',    // Tu dominio real en producción
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // 🔒 VITAL PARA SANCTUM: Permite el envío de Cookies y credenciales HTTP
    'supports_credentials' => true,

];