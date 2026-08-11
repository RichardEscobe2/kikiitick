<?php

use App\Console\Commands\CancelarVentasPendientesExpiradas;
use App\Console\Commands\ReconciliarPagosPendientes;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 💳 Red de seguridad del Módulo 5 (ver docblock del comando): reconcilia contra
// Mercado Pago las órdenes pendientes que el webhook/retorno del usuario no
// lograron confirmar. withoutOverlapping evita que dos ejecuciones concurrentes
// intenten reconciliar la misma Venta si una corrida tarda más de 10 minutos.
Schedule::command(ReconciliarPagosPendientes::class)
    ->everyTenMinutes()
    ->withoutOverlapping();

// 🧹 Limpia Ventas 'pendiente' abandonadas para que no se acumulen como registros
// huérfanos ni dejen asientos bloqueados indefinidamente (ver docblock del
// comando para los umbrales por método de pago). Cada 5 minutos es suficiente
// margen frente al umbral de 15 min de tarjeta, la ventana más corta de las dos.
Schedule::command(CancelarVentasPendientesExpiradas::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();
