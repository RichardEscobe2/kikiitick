<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\TeatroController;
use App\Http\Controllers\EventoController; // <--- Controlador de Eventos
use App\Http\Controllers\CompraController;

// 1. RUTAS DE LA API
Route::prefix('api')->group(function () {

    // 📧 PROTECCIÓN DE SPAM DE CORREOS
    Route::middleware('throttle:3,1')->group(function () {
        Route::post('/enviar-codigo', [AuthController::class, 'enviarCodigo']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    });

    // 🔒 PROTECCIÓN ALTA
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/registro', [AuthController::class, 'register']);
        Route::post('/registro-organizador', [AuthController::class, 'registerOrganizador']);
        Route::post('/verificar-codigo', [AuthController::class, 'verificarCodigo']);
        Route::post('/login', [AuthController::class, 'login']);
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

    // 🔑 RUTAS AUTENTICADAS CON SANCTUM
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        Route::post('/logout', [AuthController::class, 'logout']);

        // 🏛️ RECURSOS DE ORGANIZADOR
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
        Route::post('/boletos/reservar', [CompraController::class, 'reservarAsientos']);
    Route::post('/boletos/comprar', [CompraController::class, 'procesarCompra']);
    });
});

// 2. RUTA CATCH-ALL PARA VUE (SPA)
Route::get('/{any?}', function () {
    return view('welcome');
})->where('any', '.*');