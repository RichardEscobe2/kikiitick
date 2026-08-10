<?php

namespace App\Console\Commands;

use App\Mail\ConfirmacionCompraMail;
use App\Models\Venta;
use App\Services\CompraService;
use App\Services\MercadoPagoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Red de seguridad del flujo de pago en línea (Módulo 5): si tanto el webhook de
 * Mercado Pago como el retorno del usuario a ConfirmacionCompra.vue
 * (CompraController::reconciliarPagoPendiente) fallan en confirmar un pago
 * realmente aprobado, la Venta queda atorada en 'pendiente' para siempre — sin
 * boletos ni correo de confirmación. Ocurrió en producción el 2026-08-10:
 * MERCADOPAGO_WEBHOOK_SECRET quedó desactualizado tras rotar el túnel ngrok y ~3
 * semanas de notificaciones se rechazaron por SignatureMismatch; 3 órdenes con
 * pago aprobado nunca se confirmaron hasta reconciliarlas manualmente.
 *
 * Revisa periódicamente las Ventas 'tarjeta'/'oxxo' pendientes con más de
 * UMBRAL_MINUTOS de antigüedad (para no pisar el camino normal de confirmación
 * inmediata al volver del checkout) y las re-consulta contra la API de Mercado
 * Pago por external_reference.
 *
 * Idempotente: reutiliza CompraService::confirmarPagoAprobado(), que ya usa
 * lockForUpdate y no confirma dos veces la misma Venta (RN-11).
 */
class ReconciliarPagosPendientes extends Command
{
    protected $signature = 'kikiitick:reconciliar-pagos-pendientes';

    protected $description = 'Re-consulta contra la API de Mercado Pago las órdenes pendientes cuyo pago pudo aprobarse sin que el webhook ni el retorno del usuario las confirmaran.';

    private const UMBRAL_MINUTOS = 10;

    public function __construct(
        private CompraService $compraService,
        private MercadoPagoService $mercadoPago,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $ventas = Venta::whereIn('metodo_pago', ['tarjeta', 'oxxo'])
            ->where('estatus_pago', 'pendiente')
            ->where('fecha_venta', '<=', now()->subMinutes(self::UMBRAL_MINUTOS))
            ->get();

        $confirmadas = 0;

        foreach ($ventas as $venta) {
            try {
                $pago = $this->mercadoPago->buscarPagoAprobado($venta);
            } catch (\Throwable $e) {
                Log::warning('Reconciliación programada: no se pudo consultar el pago en Mercado Pago.', [
                    'venta_id'  => $venta->id,
                    'exception' => $e,
                ]);

                continue;
            }

            if (!$pago) {
                continue;
            }

            $seConfirmoAhora = $this->compraService->confirmarPagoAprobado($venta);

            if ($seConfirmoAhora) {
                $venta->refresh()->load(['usuario', 'accesos', 'detalles.boletoEvento.zonaTeatro', 'detalles.boletoEvento.evento.teatro']);

                if ($venta->usuario?->correo) {
                    Mail::to($venta->usuario->correo)->queue(new ConfirmacionCompraMail($venta));
                }

                $confirmadas++;
            }
        }

        $this->info("Reconciliación programada: {$confirmadas} orden(es) confirmada(s) de {$ventas->count()} revisada(s).");

        return self::SUCCESS;
    }
}
