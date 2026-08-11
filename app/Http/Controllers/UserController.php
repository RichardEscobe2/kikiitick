<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePerfilRequest;

class UserController extends Controller
{
    /**
     * PATCH /api/user/perfil — actualización parcial del perfil del propio
     * usuario autenticado (nombre, teléfono, RFC). 'correo' y 'rol' NUNCA se
     * aceptan aquí (no están en las reglas de UpdatePerfilRequest ni se leen
     * de $request->all()) — cambiar el correo o el rol de la propia cuenta
     * son acciones distintas y más sensibles, fuera de alcance de este
     * endpoint de auto-servicio.
     */
    public function actualizarPerfil(UpdatePerfilRequest $request)
    {
        $usuario = $request->user();
        $usuario->update($request->validated());

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'user'    => $usuario->fresh(),
        ], 200);
    }
}
