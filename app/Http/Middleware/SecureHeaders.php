<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecureHeaders
{
    /**
     * 🛡️ No interfiere con CORS: HandleCors está registrado antes que este middleware en el
     * stack global (bootstrap/app.php) y corta el flujo devolviendo su propia respuesta para
     * peticiones OPTIONS de preflight SIN llegar a invocar $next() — por lo que este middleware
     * nunca se ejecuta en un preflight real. Para peticiones normales, HandleCors decora la
     * respuesta con Access-Control-Allow-* DESPUÉS de que este middleware corre (más cerca del
     * navegador en la cadena), así que sus cabeceras nunca se pisan. El guard de OPTIONS de abajo
     * es una red de seguridad adicional por si el orden del stack cambia en el futuro.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('OPTIONS')) {
            return $response;
        }

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
