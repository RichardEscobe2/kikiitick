<?php

namespace App\Http\Controllers;

use App\Models\Teatro;
use App\Models\User;
use App\Mail\OrganizadorAprobadoMail;
use App\Services\SeatGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
            // 'vendedor' (Módulo 5, RF-10/RN-09): personal autorizado para vender en taquilla
            'rol' => 'required|in:cliente,organizador,admin,vendedor'
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

    // Obtener solicitudes de organizadores pendientes con su recinto/teatro inicial.
    // `teatros`: cubre las solicitudes que vinieron de registerOrganizador() (invitado,
    // crea el Teatro de una vez). `solicitudOrganizador`: cubre las que vinieron del
    // auto-servicio (AuthController::solicitudOrganizador), donde el Teatro todavía
    // no existe — el frontend usa una u otra según cuál venga poblada.
    public function getSolicitudesOrganizador()
    {
        $solicitudes = User::with(['teatros', 'solicitudOrganizador'])
            ->where('estatus_organizador', 'pendiente')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($solicitudes);
    }

    // Aprobar solicitud de organizador
    public function aprobarOrganizador($id)
    {
        $usuario = User::with(['teatros', 'solicitudOrganizador'])->findOrFail($id);

        DB::transaction(function () use ($usuario) {
            // 🏛️ Si la solicitud vino del auto-servicio (no de registerOrganizador,
            // que ya crea el Teatro al registrarse), todavía no existe ningún
            // recinto — se crea aquí a partir de lo que el solicitante propuso,
            // con los mismos valores por defecto de matriz de asientos que usa
            // registerOrganizador() para invitados (15 filas x 20 asientos, 2
            // pasillos, escenario arriba).
            if ($usuario->teatros->isEmpty() && $usuario->solicitudOrganizador) {
                $solicitud = $usuario->solicitudOrganizador;

                $teatro = Teatro::create([
                    'usuario_id'         => $usuario->id,
                    'nombre'             => $solicitud->recinto_nombre,
                    'ubicacion'          => $solicitud->recinto_direccion,
                    'capacidad_total'    => $solicitud->recinto_capacidad,
                    'filas_totales'      => 15,
                    'asientos_por_fila'  => 20,
                    'pasillos_slots'     => [5, 15],
                    'posicion_escenario' => 'arriba',
                ]);

                SeatGeneratorService::generarAsientosParaTeatro($teatro);
            }

            $usuario->rol = 'organizador';
            $usuario->estatus_organizador = 'aprobado';
            $usuario->save();
        }, attempts: 3);

        // Enviar correo de notificación de forma síncrona (garantiza entrega real; no depende de un worker de cola)
        try {
            Mail::to($usuario->correo)->send(new OrganizadorAprobadoMail($usuario));
        } catch (\Exception $e) {
            Log::error('Error al enviar el correo de aprobación de organizador: ' . $e->getMessage(), [
                'usuario_id' => $usuario->id,
            ]);
        }

        return response()->json([
            'mensaje' => "El usuario {$usuario->nombre} ha sido aprobado como organizador exitosamente.",
            'usuario' => $usuario->fresh(['teatros', 'solicitudOrganizador'])
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

    /**
     * GET /api/admin/logs/auditoria — lee y parsea el canal 'auditoria'
     * (config/logging.php, driver 'daily') para el visor de auditoría del
     * panel de admin. Nunca se re-escribe el archivo, solo lectura.
     *
     * Formato de cada línea (Monolog LineFormatter estándar de Laravel):
     * "[2026-08-11 14:30:45] local.INFO: Login exitoso {"usuario_id":52,...}"
     */
    public function getLogsAuditoria(Request $request)
    {
        $archivos = glob(storage_path('logs/auditoria-*.log')) ?: [];
        // El driver 'daily' también puede dejar un 'auditoria.log' sin fecha
        // en instalaciones muy nuevas (primer día antes de rotar) — se incluye
        // también por si acaso.
        $archivoBase = storage_path('logs/auditoria.log');
        if (file_exists($archivoBase)) {
            $archivos[] = $archivoBase;
        }

        $patron = '/^\[(?<timestamp>[\d\-]+ [\d:]+)\]\s+\S+\.(?<level>\w+):\s+(?<mensaje>.+?)\s+(?<contexto>\{.*\})\s*$/';

        $mapaTipoEvento = [
            'Login exitoso'                              => 'login_exitoso',
            'Intento de login fallido'                    => 'login_fallido',
            'Intento de login con cuenta no verificada'    => 'login_fallido',
            'Logout'                                       => 'logout',
        ];

        $entradas = [];

        foreach ($archivos as $archivo) {
            $lineas = @file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

            foreach ($lineas as $linea) {
                if (!preg_match($patron, $linea, $m)) {
                    continue; // línea de continuación de una traza previa, etc. — se ignora
                }

                $contexto = json_decode($m['contexto'], true) ?? [];

                $entradas[] = [
                    'timestamp'  => $m['timestamp'],
                    'event_type' => $mapaTipoEvento[$m['mensaje']] ?? 'otro',
                    'mensaje'    => $m['mensaje'],
                    'usuario_id' => $contexto['usuario_id'] ?? null,
                    'correo'     => $contexto['correo'] ?? $contexto['correo_intentado'] ?? null,
                    'ip'         => $contexto['ip'] ?? null,
                ];
            }
        }

        // Más reciente primero.
        usort($entradas, fn ($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

        // Límite razonable de payload — el visor no necesita el historial
        // completo de meses, solo actividad reciente para auditar accesos.
        return response()->json(array_slice($entradas, 0, 500));
    }
}