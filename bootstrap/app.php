<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 🌐 Resolución dinámica de host/esquema (localhost ↔ túnel ngrok): Nginx
        // reenvía X-Forwarded-Proto/Host a PHP-FPM automáticamente (el protocolo
        // FastCGI pasa cualquier header del cliente como HTTP_*), pero Laravel
        // los ignora por defecto hasta que se declara el proxy como confiable.
        // 'at: *' es seguro aquí porque PHP-FPM (puerto 9000) nunca se expone al
        // host ni a internet — solo el contenedor `web` (nuestro propio Nginx)
        // puede alcanzarlo dentro de la red de docker-compose, así que "cualquier
        // IP que llegue a FastCGI" ya es, por construcción, nuestro propio proxy
        // de confianza. Con esto, request()->isSecure() (AppServiceProvider) y
        // request()->getHost() (usado por asset()/url() al quedar ASSET_URL
        // vacío) reflejan correctamente si el acceso real fue por
        // http://localhost o por https://*.ngrok-free.dev, sin tocar .env.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

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