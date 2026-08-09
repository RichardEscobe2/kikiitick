<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsOrganizador
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Un admin siempre tiene acceso (paridad con TeatroController::verificarEstatusOrganizador
        // y EventoController); cualquier otro usuario debe ser organizador aprobado.
        $esOrganizadorAprobado = $user
            && $user->rol === 'organizador'
            && $user->estatus_organizador === 'aprobado';

        if (! $user || (! $esOrganizadorAprobado && $user->rol !== 'admin')) {
            return response()->json([
                'message' => 'Acceso denegado. Se requiere una cuenta de organizador aprobada.'
            ], 403);
        }

        return $next($request);
    }
}
