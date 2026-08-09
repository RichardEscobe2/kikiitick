<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Teatro;
use App\Mail\CodigoVerificacionMail;
use App\Services\SeatGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    /**
     * Envía el código de verificación de forma síncrona (garantiza entrega real a Mailtrap/SMTP
     * en vez de depender de un worker de cola que puede no estar corriendo). Los fallos de envío
     * se capturan y registran para no interrumpir el flujo de autenticación.
     */
    private function enviarCodigoPorCorreo(User $usuario, string $codigo): void
    {
        try {
            Mail::to($usuario->correo)->send(new CodigoVerificacionMail($codigo));
        } catch (\Exception $e) {
            Log::error('Error al enviar el correo de código de verificación: ' . $e->getMessage(), [
                'usuario_id' => $usuario->id,
            ]);
        }
    }
    public function register(Request $request)
    {
        $validated = $request->validate([
            'nombre'     => 'required|string|max:255',
            'correo'     => 'required|string|email|max:255|unique:usuarios,correo',
            'contrasena' => 'required|string|min:8',
        ]);

        // 🛡️ RN-02: el registro público nunca debe permitir auto-asignación de rol.
        // Todo registro entra como 'cliente'; la promoción a 'organizador'/'admin'
        // ocurre exclusivamente vía registerOrganizador() o AdminController::cambiarRol().
        $rol = 'cliente';
        $estatusOrganizador = 'ninguno';

        $codigo = (string) random_int(100000, 999999);

        $usuario = User::create([
            'nombre'              => $validated['nombre'],
            'correo'              => strtolower(trim($validated['correo'])),
            'contrasena'          => Hash::make($validated['contrasena']),
            'rol'                 => $rol,
            'estatus_organizador' => $estatusOrganizador,
            'codigo_verificacion' => $codigo,
            'codigo_expira_en'    => now()->addMinutes(10),
        ]);

        $this->enviarCodigoPorCorreo($usuario, $codigo);

        return response()->json([
            'message' => 'Usuario registrado exitosamente. Código enviado.',
            'user'    => $usuario
        ], 201);
    }

    public function registerOrganizador(Request $request)
    {
        $validated = $request->validate([
            // Datos del Usuario
            'nombre'                    => 'required|string|max:255',
            'correo'                    => 'required|string|email|max:255|unique:usuarios,correo',
            'contrasena'                => 'required|string|min:8',
            
            // Datos del Teatro / Recinto
            'teatro_nombre'             => 'required|string|max:255',
            'teatro_ubicacion'          => 'required|string|max:255',
            'teatro_capacidad'          => 'required|integer|min:1',
            'teatro_filas_totales'      => 'nullable|integer|min:1|max:26',
            'teatro_asientos_por_fila'  => 'nullable|integer|min:1',
            'teatro_pasillos_slots'     => 'nullable|array',
            'teatro_posicion_escenario' => 'nullable|in:arriba,centro',
        ], [
            'correo.unique' => 'Este correo electrónico ya está registrado.'
        ]);

        $codigo = (string) random_int(100000, 999999);

        // 🛡️ Atomicidad: si Teatro::create() o la generación de asientos fallan tras
        // crear el User, la transacción hace rollback completo — evita un usuario
        // 'organizador' huérfano sin recinto asociado. attempts:3 reintenta ante
        // deadlocks transitorios (la generación de asientos usa lockForUpdate internamente).
        [$usuario, $teatro] = DB::transaction(function () use ($validated, $codigo) {
            $usuario = User::create([
                'nombre'              => $validated['nombre'],
                'correo'              => strtolower(trim($validated['correo'])),
                'contrasena'          => Hash::make($validated['contrasena']),
                'rol'                 => 'cliente',
                'estatus_organizador' => 'pendiente',
                'codigo_verificacion' => $codigo,
                'codigo_expira_en'    => now()->addMinutes(10),
            ]);

            $teatro = Teatro::create([
                'usuario_id'         => $usuario->id,
                'nombre'             => $validated['teatro_nombre'],
                'ubicacion'          => $validated['teatro_ubicacion'],
                'capacidad_total'    => $validated['teatro_capacidad'],
                'filas_totales'      => $validated['teatro_filas_totales'] ?? 15,
                'asientos_por_fila'  => $validated['teatro_asientos_por_fila'] ?? 20,
                'pasillos_slots'     => $validated['teatro_pasillos_slots'] ?? [5, 15],
                'posicion_escenario' => $validated['teatro_posicion_escenario'] ?? 'arriba',
            ]);

            // Generar matriz inicial
            SeatGeneratorService::generarAsientosParaTeatro($teatro);

            return [$usuario, $teatro];
        }, attempts: 3);

        $this->enviarCodigoPorCorreo($usuario, $codigo);

        return response()->json([
            'message' => 'Solicitud enviada exitosamente. Verifica tu correo para activar tu cuenta.',
            'user'    => $usuario
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'correo'     => 'required|email',
            'contrasena' => 'required|string',
        ]);

        $usuario = User::where('correo', $request->correo)->first();

        if (!$usuario || !Hash::check($request->contrasena, $usuario->contrasena)) {
            return response()->json([
                'message' => 'Las credenciales proporcionadas son incorrectas.'
            ], 401);
        }

        // 🛡️ Bloquea el login si la cuenta no ha completado la verificación por OTP/correo.
        if (!$usuario->correo_verificado_at) {
            return response()->json([
                'message' => 'Account not verified. Please check your email for the verification code.'
            ], 403);
        }

        Auth::login($usuario);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Inicio de sesión exitoso.',
            'user'    => $usuario
        ], 200);
    }

    public function enviarCodigo(Request $request)
    {
        $request->validate([
            'correo' => 'required|email|exists:usuarios,correo',
        ]);

        $usuario = User::where('correo', $request->correo)->first();
        $codigo = (string) random_int(100000, 999999);

        $usuario->update([
            'codigo_verificacion' => $codigo,
            'codigo_expira_en'    => now()->addMinutes(10),
        ]);

        $this->enviarCodigoPorCorreo($usuario, $codigo);

        return response()->json([
            'message' => 'Código de verificación enviado a tu correo.'
        ], 200);
    }

    public function verificarCodigo(Request $request)
    {
        $request->validate([
            'correo' => 'required|email|exists:usuarios,correo',
            'codigo' => 'required|string|size:6',
        ]);

        $usuario = User::where('correo', $request->correo)->first();

        if (! hash_equals((string) $usuario->codigo_verificacion, (string) $request->codigo)) {
            return response()->json([
                'message' => 'El código ingresado es incorrecto.'
            ], 422);
        }

        if ($usuario->codigo_expira_en && now()->greaterThan($usuario->codigo_expira_en)) {
            return response()->json([
                'message' => 'El código ha expirado. Solicita uno nuevo.'
            ], 422);
        }

        $usuario->update([
            'correo_verificado_at' => now(),
            'codigo_verificacion' => null,
            'codigo_expira_en'    => null,
        ]);

        Auth::login($usuario);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Verificación exitosa.',
            'user'    => $usuario
        ], 200);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.'
        ], 200);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'correo' => 'required|email|exists:usuarios,correo',
        ], [
            'correo.exists' => 'No encontramos ninguna cuenta registrada con este correo electrónico.'
        ]);

        $usuario = User::where('correo', $request->correo)->first();
        $codigo = (string) random_int(100000, 999999);

        $usuario->update([
            'codigo_verificacion' => $codigo,
            'codigo_expira_en'    => now()->addMinutes(10),
        ]);

        $this->enviarCodigoPorCorreo($usuario, $codigo);

        return response()->json([
            'message' => 'Código de recuperación enviado a tu correo.'
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'correo'     => 'required|email|exists:usuarios,correo',
            'codigo'     => 'required|string|size:6',
            'contrasena' => 'required|string|min:8|confirmed',
        ], [
            'contrasena.confirmed' => 'Las contraseñas no coinciden.',
            'contrasena.min'       => 'La contraseña debe tener al menos 8 caracteres.'
        ]);

        $usuario = User::where('correo', $request->correo)->first();

        if (! hash_equals((string) $usuario->codigo_verificacion, (string) $request->codigo)) {
            return response()->json([
                'message' => 'El código de verificación es incorrecto.'
            ], 422);
        }

        if ($usuario->codigo_expira_en && now()->greaterThan($usuario->codigo_expira_en)) {
            return response()->json([
                'message' => 'El código ha expirado. Solicita uno nuevo.'
            ], 422);
        }

        $usuario->update([
            'contrasena'          => Hash::make($request->contrasena),
            'codigo_verificacion' => null,
            'codigo_expira_en'    => null,
        ]);

        return response()->json([
            'message' => 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.'
        ], 200);
    }
}