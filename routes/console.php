<?php

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
