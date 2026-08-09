<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\TeatroController;
use App\Http\Controllers\EventoController; // <--- Controlador de Eventos
use App\Http\Controllers\CompraController;
use App\Http\Controllers\TaquillaController;

// 1. RUTAS DE LA API
Route::prefix('api')->group(function () {

    // 📧 PROTECCIÓN DE SPAM DE CORREOS
    Route::middleware('throttle:3,1')->group(function () {
        Route::post('/enviar-codigo', [AuthController::class, 'enviarCodigo']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    });

    // 🔒 PROTECCIÓN ALTA — login/registro directo del cliente (límite ajustable por
    // entorno vía el rate limiter nombrado 'auth-throttle' en AppServiceProvider)
    Route::middleware('throttle:auth-throttle')->group(function () {
        Route::post('/registro', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // 🔒 PROTECCIÓN ALTA — verificación OTP y recuperación de contraseña (límite fijo,
    // sin relajar en ningún entorno: son los endpoints más sensibles a fuerza bruta)
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/registro-organizador', [AuthController::class, 'registerOrganizador']);
        Route::post('/verificar-codigo', [AuthController::class, 'verificarCodigo']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    // 🛡️ PROTECCIÓN ESTÁNDAR
    Route::middleware('throttle:60,1')->group(function () {
        
        // Obtener eventos activos
        Route::get('/eventos', function () {
            return DB::table('eventos')
                ->join('teatros', 'eventos.teatro_id', '=', 'teatros.id')
                ->select('eventos.*', 'teatros.nombre as teatro_nombre')
                ->where('eventos.estatus', 'activo')
                ->get();
        });

        // 🔹 NUEVA RUTA FASE 3: Mapa interactivo y disponibilidad del evento
        Route::get('/eventos/{id}/mapa', [EventoController::class, 'getMapaEvento']);
    });

    // 💳 WEBHOOK DE MERCADO PAGO (ruta pública: sin auth:sanctum ni CSRF — ver
    // bootstrap/app.php `preventRequestForgery(except: ...)`. La autenticidad se
    // verifica dentro del controlador vía firma HMAC, no vía middleware de sesión).
    Route::middleware('throttle:120,1')->group(function () {
        Route::post('/pagos/webhook', [CompraController::class, 'webhookMercadoPago']);
    });

    // 🛡️ RUTAS ADMINISTRATIVAS (RN-02: requieren sesión Sanctum + rol 'admin')
    Route::middleware(['auth:sanctum', 'admin', 'throttle:60,1'])->prefix('admin')->group(function () {
        // Rutas de Administración
        Route::get('/usuarios', [AdminController::class, 'index']);
        Route::put('/usuarios/{id}/rol', [AdminController::class, 'cambiarRol']);
        Route::delete('/usuarios/{id}', [AdminController::class, 'destroy']);
        Route::put('/usuarios/{id}/contrasena', [AdminController::class, 'cambiarContrasena']);

        // Rutas de Solicitudes de Organizador
        Route::get('/solicitudes-organizador', [AdminController::class, 'getSolicitudesOrganizador']);
        Route::put('/organizador/{id}/aprobar', [AdminController::class, 'aprobarOrganizador']);
        Route::put('/organizador/{id}/rechazar', [AdminController::class, 'rechazarOrganizador']);
    });

    // 🔑 RUTAS AUTENTICADAS CON SANCTUM (cualquier usuario autenticado, sin restricción de rol)
    Route::middleware('auth:sanctum')->group(function () {
        // 🏧 load('taquilla') es aditivo: solo agrega una clave anidada 'taquilla' al
        // JSON de siempre, para que Navbar.vue pueda mostrar "Caja: {nombre}" en el
        // badge de cajero sin una petición aparte. No afecta a ningún otro consumidor
        // de este endpoint (clientes/organizadores/admins simplemente reciben taquilla: null).
        Route::get('/user', function (Request $request) {
            return $request->user()->load('taquilla');
        });

        Route::post('/logout', [AuthController::class, 'logout']);

        // 🎫 Compra/reserva de boletos: acción de cualquier cliente autenticado
        Route::post('/boletos/reservar', [CompraController::class, 'reservarAsientos']);
        Route::post('/boletos/comprar', [CompraController::class, 'procesarCompra']);

        // 🏧 RF-10/RN-09: venta directa en taquilla. Autorización por rol (vendedor,
        // organizador, admin) se valida DENTRO del controlador, no vía route middleware,
        // porque ningún middleware de rol existente cubre esta combinación de 3 roles.
        Route::post('/boletos/comprar-pos', [CompraController::class, 'comprarPos']);

        // 🧾 Detalle de orden para ConfirmacionCompra.vue / FichaOxxo.vue (autorización
        // por propiedad dentro del controlador)
        Route::get('/ventas/{id}', [CompraController::class, 'mostrarVenta']);

        // 🧪 Simulación de pago aprobado — SOLO fuera de producción (verificado dentro
        // del controlador), para probar el flujo de OXXO sin esperar el pago real.
        Route::post('/ventas/{id}/simular-pago', [CompraController::class, 'simularPago']);

        // 🎫 Historial de compras completadas del usuario, para "Mis Boletos"
        Route::get('/mis-boletos', [CompraController::class, 'misBoletos']);
    });

    // 🏛️ RUTAS DE ORGANIZADOR (RN-02: requieren sesión Sanctum + rol 'organizador' aprobado, o admin)
    Route::middleware(['auth:sanctum', 'organizador'])->group(function () {
        Route::get('/organizador/teatros', [TeatroController::class, 'index']);
        Route::post('/organizador/teatros', [TeatroController::class, 'store']);
        Route::put('/organizador/teatros/{id}', [TeatroController::class, 'update']);
        Route::delete('/organizador/teatros/{id}', [TeatroController::class, 'destroy']);

        Route::post('/organizador/teatros/{id}/zonas', [TeatroController::class, 'storeZona']);
        Route::delete('/organizador/zonas/{id}', [TeatroController::class, 'destroyZona']);

        // 🎟️ EVENTOS
        Route::get('/organizador/eventos', [EventoController::class, 'index']);
        Route::post('/organizador/eventos', [EventoController::class, 'store']);
        Route::put('/organizador/eventos/{id}', [EventoController::class, 'update']);
        Route::delete('/organizador/eventos/{id}', [EventoController::class, 'destroy']);

        Route::get('/organizador/eventos/{id}/precios', [EventoController::class, 'getPrecios']);
        Route::post('/organizador/eventos/{id}/precios', [EventoController::class, 'guardarPrecios']);

        // 🏧 Gestión de taquillas y cajeros (Módulo 5, Fase 2) — mismo middleware
        // ['auth:sanctum', 'organizador'] de arriba (organizador aprobado o admin);
        // el ownership por recinto/organizador_padre_id se verifica dentro del
        // controlador (TaquillaController), igual que en TeatroController.
        Route::get('/organizador/taquillas', [TaquillaController::class, 'index']);
        Route::post('/organizador/taquillas', [TaquillaController::class, 'store']);
        Route::put('/organizador/taquillas/{id}', [TaquillaController::class, 'update']);
        Route::get('/organizador/cajeros', [TaquillaController::class, 'indexCajeros']);
        Route::post('/organizador/cajeros', [TaquillaController::class, 'storeCajero']);
        Route::put('/organizador/cajeros/{id}', [TaquillaController::class, 'updateCajero']);
        Route::delete('/organizador/cajeros/{id}', [TaquillaController::class, 'destroyCajero']);
        Route::put('/organizador/cajeros/{id}/contrasena', [TaquillaController::class, 'resetPasswordCajero']);
    });
});

// 2. RUTA CATCH-ALL PARA VUE (SPA)
Route::get('/{any?}', function () {
    return view('welcome');
})->where('any', '.*');