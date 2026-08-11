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
    public function destroy(Request $request, $id)
    {
        $usuario = User::findOrFail($id);
        $solicitante = $request->user();

        // 🛡️ OWASP A01 (Broken Access Control): un admin nunca debe poder
        // auto-eliminarse (podría dejar la plataforma sin ningún admin activo)
        // ni eliminar a otro admin (evita que una cuenta admin comprometida
        // borre a las demás para cubrir sus huellas). Se revisa la
        // auto-eliminación primero para dar un mensaje más específico que el
        // genérico de "cuenta de admin protegida".
        if ($solicitante->id === $usuario->id) {
            Log::channel('auditoria')->warning('AUDITORIA_SEGURIDAD: Intento no autorizado de eliminación de cuenta admin', [
                'attempted_by' => $solicitante->id,
                'target_user'  => $usuario->id,
                'motivo'       => 'auto_eliminacion',
            ]);

            return response()->json([
                'message' => 'No puedes eliminar tu propia cuenta de administrador.'
            ], 403);
        }

        if ($usuario->rol === 'admin') {
            Log::channel('auditoria')->warning('AUDITORIA_SEGURIDAD: Intento no autorizado de eliminación de cuenta admin', [
                'attempted_by' => $solicitante->id,
                'target_user'  => $usuario->id,
                'motivo'       => 'cuenta_admin_protegida',
            ]);

            return response()->json([
                'message' => 'Las cuentas de administrador son protegidas y no pueden ser eliminadas.'
            ], 403);
        }

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
     * Prefijos reales que el código emite hoy (grep de
     * `Log::channel('auditoria')` en toda la app) mapeados a una categoría
     * para el badge del visor. Ordenados de más a menos específico no hace
     * falta: ningún prefijo real es substring de otro salvo a propósito
     * (AUDITORIA_RESERVA / AUDITORIA_RESERVA_EXPIRADA, AUDITORIA_VENTA_WEB /
     * AUDITORIA_VENTA_POS comparten categoría intencionalmente). Categorías
     * como 'gestion_recinto'/'gestion_evento'/'cambio_password' están
     * reconocidas aquí para cuando existan (forward-compatible), aunque
     * ningún controlador las emite todavía — no es una promesa de que ya
     * funcionen, solo que el visor no las mostrará como "Otro" el día que se
     * agreguen.
     */
    private const CATEGORIAS_AUDITORIA = [
        'AUDITORIA_AUTH_REGISTRO'         => ['autenticacion', 'Autenticación'],
        'AUDITORIA_AUTH_LOGIN'            => ['autenticacion', 'Autenticación'],
        'AUDITORIA_AUTH_LOGOUT'           => ['autenticacion', 'Autenticación'],
        'AUDITORIA_AUTH_PASSWORD'         => ['cambio_password', 'Cambio Contraseña'],
        'AUDITORIA_SOLICITUD_ORGANIZADOR' => ['solicitud_organizador', 'Solicitud Organizador'],
        'AUDITORIA_RECINTO'               => ['gestion_recinto', 'Gestión Recinto'],
        'AUDITORIA_EVENTO'                => ['gestion_evento', 'Gestión Evento'],
        'AUDITORIA_RESERVA'               => ['apartado_boleto', 'Apartado Boleto'],
        'AUDITORIA_VENTA'                 => ['compra_boleto', 'Compra Boleto'],
        'AUDITORIA_SEGURIDAD'             => ['seguridad', 'Seguridad'],
    ];

    /**
     * Mensajes de auditoría de login/logout emitidos ANTES de que
     * AuthController empezara a usar el prefijo AUDITORIA_AUTH_LOGIN/LOGOUT —
     * cualquier entrada real ya escrita en storage/logs/ con el texto viejo
     * (sin prefijo) seguiría cayendo en 'otro' sin este mapa de compatibilidad,
     * a pesar de ser un evento de autenticación genuino.
     */
    private const CATEGORIA_LEGADO = [
        'Login exitoso'                             => ['autenticacion'],
        'Intento de login fallido'                  => ['autenticacion'],
        'Intento de login con cuenta no verificada' => ['autenticacion'],
        'Logout'                                    => ['autenticacion'],
    ];

    /**
     * GET /api/admin/logs/auditoria — lee y parsea el canal 'auditoria'
     * (config/logging.php, driver 'daily') para el visor de auditoría del
     * panel de admin. Nunca se re-escribe el archivo, solo lectura.
     *
     * Formato de cada línea (Monolog LineFormatter estándar de Laravel):
     * "[2026-08-11 14:30:45] local.INFO: AUDITORIA_VENTA_WEB: Venta exitosa {"usuario_id":52,...}"
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

        $entradas = [];

        foreach ($archivos as $archivo) {
            $lineas = @file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

            foreach ($lineas as $linea) {
                if (!preg_match($patron, $linea, $m)) {
                    continue; // línea de continuación de una traza previa, etc. — se ignora
                }

                $contexto = json_decode($m['contexto'], true) ?? [];

                // Separa "AUDITORIA_XXX: Descripción legible" en tag + descripción.
                // Si un mensaje algún día no trae ":", se trata todo como descripción
                // y el tag queda vacío (cae en categoría 'otro' abajo).
                [$tag, $descripcion] = array_pad(explode(': ', $m['mensaje'], 2), 2, $m['mensaje']);

                $categoria = self::CATEGORIA_LEGADO[$m['mensaje']][0] ?? 'otro';
                foreach (self::CATEGORIAS_AUDITORIA as $prefijo => [$catKey, $catLabel]) {
                    if (str_starts_with($tag, $prefijo)) {
                        $categoria = $catKey;
                        break;
                    }
                }

                // 🛡️ Cada tipo de evento nombra su usuario/correo con claves distintas
                // según el contexto de negocio (quién vende vs. quién compra, quién
                // intentó la acción vs. el objetivo) — antes solo se leía
                // 'usuario_id'/'correo', así que AUDITORIA_VENTA_POS (vendedor_usuario_id
                // + cliente_email) y AUDITORIA_SEGURIDAD (attempted_by) siempre
                // mostraban "Desconocido" aunque el dato SÍ estaba en el contexto.
                $usuarioId = $contexto['usuario_id']
                    ?? $contexto['vendedor_usuario_id']
                    ?? $contexto['attempted_by']
                    ?? null;

                $correo = $contexto['correo']
                    ?? $contexto['correo_intentado']
                    ?? $contexto['cliente_email']
                    ?? null;

                $entradas[] = [
                    'timestamp'   => $m['timestamp'],
                    'category'    => $categoria,
                    'event_name'  => $categoria === 'otro' ? $tag : trim($descripcion),
                    'mensaje'     => $m['mensaje'],
                    'usuario_id'  => $usuarioId,
                    'correo'      => $correo,
                    'ip'          => $contexto['ip'] ?? null,
                ];
            }
        }

        // 🛡️ Resuelve el correo por lookup de BD cuando el contexto del log trae
        // usuario_id pero no un correo directo (ej. AUDITORIA_RESERVA/VENTA_WEB
        // nunca guardaron el correo, solo el id) — así el frontend nunca muestra
        // "Desconocido" para una entrada que sí tiene un usuario identificable.
        $idsSinCorreo = collect($entradas)
            ->filter(fn ($e) => $e['usuario_id'] && !$e['correo'])
            ->pluck('usuario_id')
            ->unique();

        if ($idsSinCorreo->isNotEmpty()) {
            $correosPorId = User::whereIn('id', $idsSinCorreo)->pluck('correo', 'id');

            foreach ($entradas as &$entrada) {
                if ($entrada['usuario_id'] && !$entrada['correo']) {
                    $entrada['correo'] = $correosPorId->get($entrada['usuario_id']);
                }
            }
            unset($entrada);
        }

        // Más reciente primero.
        usort($entradas, fn ($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

        // Límite razonable de payload — el visor no necesita el historial
        // completo de meses, solo actividad reciente para auditar accesos.
        return response()->json(array_slice($entradas, 0, 500));
    }
}