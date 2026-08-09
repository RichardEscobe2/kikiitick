<?php

namespace App\Http\Controllers;

use App\Models\Taquilla;
use App\Models\Teatro;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TaquillaController extends Controller
{
    // 🛡️ Solo letras (incluye acentos/ñ) y espacios — bloquea números, símbolos y
    // marcado HTML/script en el nombre de una persona. El modificador /u es
    // obligatorio: sin él, PCRE trata el patrón como bytes crudos y los caracteres
    // acentuados (codificados como multibyte en UTF-8) no coinciden de forma fiable.
    private const REGEX_NOMBRE_PERSONA = '/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u';
    private const MSG_NOMBRE_PERSONA = 'El nombre del vendedor solo puede contener letras y espacios.';

    // 🛡️ Letras, números y espacios — permite "Caja 1"/"Taquilla Norte 2" pero
    // bloquea <, >, ', ", /, ;, $ y cualquier otro vector de inyección de código.
    private const REGEX_NOMBRE_CAJA = '/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s]+$/u';
    private const MSG_NOMBRE_CAJA = 'El nombre de la caja solo puede contener letras, números y espacios.';

    /**
     * Un admin siempre tiene acceso a todo; un organizador solo a sus propios
     * recursos — mismo patrón de ownership que TeatroController::esPropietarioOAdmin().
     */
    private function esPropietarioOAdmin($user, int $ownerId): bool
    {
        return $user->rol === 'admin' || $user->id === $ownerId;
    }

    /**
     * 🛡️ Normaliza (trim + strip_tags) un campo de texto ANTES de que corran las
     * reglas de validación — así 'required' rechaza correctamente un valor que solo
     * contenía espacios/HTML, y las reglas regex de abajo nunca evalúan marcado sin
     * sanear. Defensa en profundidad (OWASP A03: Injection): aunque el regex ya
     * rechaza '<'/'>' explícitamente, nunca se depende de una sola capa.
     */
    private function normalizarCampoTexto(Request $request, string $campo): void
    {
        if ($request->has($campo)) {
            $request->merge([$campo => trim(strip_tags((string) $request->input($campo)))]);
        }
    }

    /**
     * GET /api/organizador/taquillas
     * Taquillas de los recintos (teatros) que pertenecen a auth()->user() — un admin
     * ve todas.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Taquilla::with('teatro');

        if ($user->rol !== 'admin') {
            $query->whereHas('teatro', fn ($q) => $q->where('usuario_id', $user->id));
        }

        return response()->json($query->orderBy('id', 'desc')->get(), 200);
    }

    /**
     * POST /api/organizador/taquillas
     * Crea una taquilla nueva en uno de los recintos del organizador autenticado.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $this->normalizarCampoTexto($request, 'nombre');

        $validated = $request->validate([
            'teatro_id' => 'required|exists:teatros,id',
            'nombre'    => ['required', 'string', 'max:255', 'regex:' . self::REGEX_NOMBRE_CAJA],
        ], [
            'nombre.regex' => self::MSG_NOMBRE_CAJA,
        ]);

        $teatro = Teatro::findOrFail($validated['teatro_id']);

        // 🛡️ BOLA: el recinto elegido debe pertenecer al organizador que hace la
        // petición (o ser admin) — sin esto, cualquier organizador podría crear
        // taquillas dentro del teatro de otro organizador con solo adivinar su id.
        if (!$this->esPropietarioOAdmin($user, $teatro->usuario_id)) {
            return response()->json([
                'message' => 'No tienes permiso para crear una taquilla en este recinto.'
            ], 403);
        }

        $taquilla = Taquilla::create([
            'teatro_id' => $teatro->id,
            'nombre'    => $validated['nombre'],
            'activa'    => true,
        ]);

        return response()->json($taquilla->load('teatro'), 201);
    }

    /**
     * PUT /api/organizador/taquillas/{id}
     * Activa/desactiva una taquilla o edita su nombre.
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $taquilla = Taquilla::with('teatro')->find($id);

        if (!$taquilla) {
            return response()->json(['message' => 'Taquilla no encontrada.'], 404);
        }

        if (!$this->esPropietarioOAdmin($user, $taquilla->teatro->usuario_id)) {
            return response()->json(['message' => 'No tienes permiso para modificar esta taquilla.'], 403);
        }

        $this->normalizarCampoTexto($request, 'nombre');

        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255', 'regex:' . self::REGEX_NOMBRE_CAJA],
            'activa' => 'sometimes|boolean',
        ], [
            'nombre.regex' => self::MSG_NOMBRE_CAJA,
        ]);

        $taquilla->update($validated);

        return response()->json($taquilla->fresh('teatro'), 200);
    }

    /**
     * GET /api/organizador/cajeros
     * Cuentas de cajero ('vendedor') que dependen del organizador autenticado
     * (organizador_padre_id) — un admin ve todas.
     */
    public function indexCajeros(Request $request)
    {
        $user = $request->user();

        $query = User::where('rol', 'vendedor')->with('taquilla.teatro');

        if ($user->rol !== 'admin') {
            $query->where('organizador_padre_id', $user->id);
        }

        // $hidden en el modelo User (contrasena, codigo_verificacion, remember_token)
        // ya se aplica automáticamente al serializar — no hace falta mapear a mano.
        return response()->json($query->orderBy('id', 'desc')->get(), 200);
    }

    /**
     * POST /api/organizador/cajeros
     * Crea una cuenta de cajero ('vendedor') a cargo del organizador autenticado,
     * opcionalmente asignada a una de sus taquillas.
     */
    public function storeCajero(Request $request)
    {
        $user = $request->user();

        $this->normalizarCampoTexto($request, 'nombre');

        $validated = $request->validate([
            'nombre'      => ['required', 'string', 'max:255', 'regex:' . self::REGEX_NOMBRE_PERSONA],
            'correo'      => 'required|email|unique:usuarios,correo',
            'taquilla_id' => 'nullable|exists:taquillas,id',
        ], [
            'nombre.regex' => self::MSG_NOMBRE_PERSONA,
        ]);

        if (!empty($validated['taquilla_id'])) {
            $taquilla = Taquilla::with('teatro')->find($validated['taquilla_id']);

            // 🛡️ BOLA: mismo chequeo que en store() — no se puede asignar un cajero a
            // una taquilla que vive en el recinto de otro organizador.
            if (!$this->esPropietarioOAdmin($user, $taquilla->teatro->usuario_id)) {
                return response()->json([
                    'message' => 'No puedes asignar una taquilla que no pertenece a uno de tus recintos.'
                ], 403);
            }
        }

        // 🛡️ Contraseña inicial generada por el servidor — se devuelve en texto
        // plano UNA sola vez en esta respuesta para que el organizador la comparta
        // con su cajero; nunca se registra en logs y no es recuperable después (se
        // guarda hasheada, igual que en AdminController::store()).
        $passwordInicial = Str::password(12, symbols: false);

        $cajero = User::create([
            'nombre'                => $validated['nombre'],
            'correo'                => $validated['correo'],
            'contrasena'            => Hash::make($passwordInicial),
            'rol'                   => 'vendedor',
            'organizador_padre_id'  => $user->id,
            'taquilla_id'           => $validated['taquilla_id'] ?? null,
            // 🛡️ Los cajeros son aprovisionados directamente por el organizador, con
            // credenciales ya conocidas y entregadas en persona/por este mismo modal —
            // no pasan por el flujo de registro público con OTP, así que no hay correo
            // de verificación que esperar. Sin esto, AuthController::login() los bloquea
            // para siempre con 403 "cuenta no verificada" (correo_verificado_at nunca se
            // habría llenado por ningún otro camino).
            'correo_verificado_at'  => now(),
        ]);

        return response()->json([
            'id'               => $cajero->id,
            'nombre'           => $cajero->nombre,
            'correo'           => $cajero->correo,
            'taquilla_id'      => $cajero->taquilla_id,
            'password_inicial' => $passwordInicial,
        ], 201);
    }

    /**
     * PUT /api/organizador/cajeros/{id}
     * Edita nombre, correo y/o taquilla asignada de un cajero a cargo del
     * organizador autenticado (o cualquiera si es admin).
     */
    public function updateCajero(Request $request, $id)
    {
        $user = $request->user();

        // 🛡️ Se filtra por rol='vendedor' en la propia consulta: este endpoint nunca
        // debe poder alcanzar un usuario que no sea cajero, sin importar el id.
        $cajero = User::where('rol', 'vendedor')->find($id);

        if (!$cajero) {
            return response()->json(['message' => 'Cajero no encontrado.'], 404);
        }

        // 🛡️ BOLA: solo el organizador dueño de este cajero (o un admin) puede editarlo.
        if (!$this->esPropietarioOAdmin($user, $cajero->organizador_padre_id)) {
            return response()->json(['message' => 'No tienes permiso para modificar este cajero.'], 403);
        }

        $this->normalizarCampoTexto($request, 'nombre');

        $validated = $request->validate([
            'nombre'      => ['sometimes', 'required', 'string', 'max:255', 'regex:' . self::REGEX_NOMBRE_PERSONA],
            'correo'      => 'sometimes|required|email|unique:usuarios,correo,' . $cajero->id,
            'taquilla_id' => 'nullable|exists:taquillas,id',
        ], [
            'nombre.regex' => self::MSG_NOMBRE_PERSONA,
        ]);

        if (!empty($validated['taquilla_id'])) {
            $taquilla = Taquilla::with('teatro')->find($validated['taquilla_id']);

            // 🛡️ BOLA: mismo chequeo que en storeCajero() — no reasignar a una taquilla
            // que vive en el recinto de otro organizador.
            if (!$this->esPropietarioOAdmin($user, $taquilla->teatro->usuario_id)) {
                return response()->json([
                    'message' => 'No puedes asignar una taquilla que no pertenece a uno de tus recintos.'
                ], 403);
            }
        }

        $cajero->update($validated);

        return response()->json($cajero->fresh('taquilla.teatro'), 200);
    }

    /**
     * DELETE /api/organizador/cajeros/{id}
     * Deshabilita (soft-delete) la cuenta de un cajero a cargo del organizador
     * autenticado. Un cajero eliminado lógicamente ya no puede iniciar sesión:
     * AuthController::login() consulta vía User::where(...)->first(), que excluye
     * automáticamente los registros con soft-delete por el trait SoftDeletes.
     */
    public function destroyCajero(Request $request, $id)
    {
        $user = $request->user();

        $cajero = User::where('rol', 'vendedor')->find($id);

        if (!$cajero) {
            return response()->json(['message' => 'Cajero no encontrado.'], 404);
        }

        if (!$this->esPropietarioOAdmin($user, $cajero->organizador_padre_id)) {
            return response()->json(['message' => 'No tienes permiso para deshabilitar este cajero.'], 403);
        }

        $cajero->delete();

        return response()->json(['message' => 'Cajero deshabilitado correctamente.'], 200);
    }

    /**
     * PUT /api/organizador/cajeros/{id}/contrasena
     * Genera y asigna una nueva contraseña temporal para un cajero a cargo del
     * organizador autenticado. Igual que en storeCajero(), la contraseña en texto
     * plano solo se devuelve UNA vez en esta respuesta — nunca se registra en logs
     * ni es recuperable después (se guarda hasheada).
     */
    public function resetPasswordCajero(Request $request, $id)
    {
        $user = $request->user();

        $cajero = User::where('rol', 'vendedor')->find($id);

        if (!$cajero) {
            return response()->json(['message' => 'Cajero no encontrado.'], 404);
        }

        if (!$this->esPropietarioOAdmin($user, $cajero->organizador_padre_id)) {
            return response()->json(['message' => 'No tienes permiso para restablecer la contraseña de este cajero.'], 403);
        }

        $passwordNueva = Str::password(12, symbols: false);

        $cajero->update(['contrasena' => Hash::make($passwordNueva)]);

        return response()->json([
            'id'               => $cajero->id,
            'correo'           => $cajero->correo,
            'password_inicial' => $passwordNueva,
        ], 200);
    }
}
