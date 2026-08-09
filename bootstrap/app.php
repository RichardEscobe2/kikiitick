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
    ->withMiddleware(function (Middleware $middleware) {
        // 🔒 Permite que Sanctum reconozca las cookies de sesión en las rutas de la API
        $middleware->statefulApi();

        // 🛡️ Cabeceras de seguridad HTTP (OWASP A05) en todas las respuestas
        $middleware->append(\App\Http\Middleware\SecureHeaders::class);

        // 💳 Webhook de Mercado Pago: petición externa servidor-a-servidor, no puede
        // llevar token CSRF de sesión. Autenticidad verificada en su lugar mediante la
        // firma HMAC del header x-signature (WebhookSignatureValidator del SDK).
        $middleware->preventRequestForgery(except: [
            'api/pagos/webhook',
        ]);

        // 🛡️ Alias para los middlewares de verificación de rol (RN-02)
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'organizador' => \App\Http\Middleware\EnsureUserIsOrganizador::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();