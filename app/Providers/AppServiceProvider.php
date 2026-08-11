<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 🛡️ Forzar HTTPS según la PETICIÓN REAL (request()->isSecure()), no según
        // config('app.url'). APP_URL apunta permanentemente al túnel ngrok (lo
        // necesitan MercadoPagoService y config/cors.php para webhooks/redirects),
        // así que un chequeo basado en config('app.url') fuerza https:// SIEMPRE,
        // incluso sirviendo por http://localhost — Nginx solo escucha en el puerto
        // 80 (sin TLS, ver docker/nginx/default.conf), así que los assets con
        // https:// forzado quedaban inalcanzables (CORS/MIME errors en el navegador).
        // request()->isSecure() sigue detectando correctamente el acceso real por
        // ngrok (HTTPS de verdad en el navegador) sin romper el acceso local.
        if (request()->isSecure()) {
            URL::forceScheme('https');
        }

        RateLimiter::for('auth-throttle', function (Request $request) {
            $maxAttempts = app()->environment('production') ? 5 : 20;

            return Limit::perMinute($maxAttempts)->by($request->ip());
        });
    }
}