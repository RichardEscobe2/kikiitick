<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\OrganizadorAprobadoMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AdminController extends Controller
{
    // Obtener la lista completa de usuarios
    public function index()
    {
        $usuarios = User::select('id', 'nombre', 'correo', 'rol', 'created_at')
                        ->orderBy('id', 'desc')
                        ->get();

        return response()->json($usuarios);
    }

    // Cambiar el rol de un usuario
    public function cambiarRol(Request $request, $id)
    {
        $request->validate([
            'rol' => 'required|in:cliente,organizador,admin' // 👈 Actualizado a 'cliente'
        ]);

        $usuario = User::findOrFail($id);
        $usuario->rol = $request->rol;
        $usuario->save();

        return response()->json([
            'mensaje' => 'Rol actualizado correctamente',
            'usuario' => $usuario
        ]);
    }

    // Eliminar lógicamente a un usuario
    public function destroy($id)
    {
        $usuario = User::findOrFail($id);
        $usuario->delete(); // Ejecuta Soft Delete automático debido al Trait

        return response()->json([
            'mensaje' => 'Usuario eliminado lógicamente con éxito.'
        ]);
    }

    // Cambiar/Restablecer contraseña de un usuario
    public function cambiarContrasena(Request $request, $id)
    {
        $request->validate([
            'contrasena' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',                  // Al menos una mayúscula
                'regex:/[0-9]/',                  // Al menos un número
                'regex:/[!@#$%^&*(),.?":{}|<>]/', // Al menos un carácter especial
            ],
        ], [
            'contrasena.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'contrasena.regex' => 'La contraseña debe tener al menos una mayúscula, un número y un carácter especial.'
        ]);

        $usuario = User::findOrFail($id);
        $usuario->contrasena = Hash::make($request->contrasena);
        $usuario->save();

        return response()->json([
            'mensaje' => "La contraseña del usuario #{$id} se ha actualizado correctamente."
        ]);
    }

    // ==========================================
    // 🆕 NUVOS MÉTODOS DE GESTIÓN DE ORGANIZADORES
    // ==========================================

    // Obtener solicitudes de organizadores pendientes con su recinto/teatro inicial
    public function getSolicitudesOrganizador()
    {
        $solicitudes = User::with('teatros')
            ->where('estatus_organizador', 'pendiente')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($solicitudes);
    }

    // Aprobar solicitud de organizador
    public function aprobarOrganizador($id)
    {
        $usuario = User::with('teatros')->findOrFail($id);

        $usuario->rol = 'organizador';
        $usuario->estatus_organizador = 'aprobado';
        $usuario->save();

        // Enviar correo de notificación
        Mail::to($usuario->correo)->send(new OrganizadorAprobadoMail($usuario));

        return response()->json([
            'mensaje' => "El usuario {$usuario->nombre} ha sido aprobado como organizador exitosamente.",
            'usuario' => $usuario
        ]);
    }

    // Rechazar solicitud de organizador
    public function rechazarOrganizador($id)
    {
        $usuario = User::findOrFail($id);

        $usuario->estatus_organizador = 'rechazado';
        $usuario->save();

        return response()->json([
            'mensaje' => "La solicitud del usuario {$usuario->nombre} ha sido rechazada.",
            'usuario' => $usuario
        ]);
    }
}