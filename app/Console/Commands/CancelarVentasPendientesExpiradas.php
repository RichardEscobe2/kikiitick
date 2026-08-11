<?php

namespace App\Console\Commands;

use App\Mail\ConfirmacionCompraMail;
use App\Models\Venta;
use App\Services\CompraService;
use App\Services\MercadoPagoService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Limpia las Ventas 'pendiente' abandonadas (checkout iniciado, pago nunca
 * completado) para que no se acumulen como registros huérfanos ni dejen asientos
 * bloqueados indefinidamente.
 *
 * El umbral depende del método de pago porque cada uno tiene una ventana de
 * vigencia distinta (ver CompraService::MINUTOS_RESERVA / HORAS_RESERVA_EFECTIVO):
 * - tarjeta: el bloqueo de asientos de RN-05 ya expiró a los 5 minutos, así que 15
 *   minutos de margen es tiempo de sobra para considerarlo abandonado.
 * - oxxo/efectivo: tiene 24h de gracia para pagarse en tienda — cancelar antes de
 *   eso invalidaría una ficha de pago todavía vigente para el cliente.
 *
 * Antes de cancelar cualquier Venta, se re-verifica contra la API de Mercado Pago
 * (igual que kikiitick:reconciliar-pagos-pendientes) — la antigüedad por sí sola
 * nunca es prueba de abandono. Si el pago sí aparece aprobado, esta corrida la
 * confirma en vez de cancelarla, autocurando el mismo escenario documentado en
 * ReconciliarPagosPendientes (webhook con firma desactualizada, etc.).
 */
class CancelarVentasPendientesExpiradas extends Command
{
    protected $signature = 'kikiitick:cancelar-ventas-pendientes-expiradas';

    protected $description = 'Cancela las Ventas pendientes abandonadas (checkout sin completar) y libera sus asientos, verificando primero contra Mercado Pago que el pago realmente no se aprobó.';

    private const MINUTOS_TARJETA = 15;

    private const HORAS_EFECTIVO = 24;

    public function __construct(
        private CompraService $compraService,
        private MercadoPagoService $mercadoPago,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $canceladas = $this->procesarMetodo('tarjeta', now()->subMinutes(self::MINUTOS_TARJETA))
            + $this->procesarMetodo('oxxo', now()->subHours(self::HORAS_EFECTIVO));

        $this->info("Ventas pendientes canceladas: {$canceladas}.");

        return self::SUCCESS;
    }

    private function procesarMetodo(string $metodoPago, Carbon $umbral): int
    {
        $ventas = Venta::where('metodo_pago', $metodoPago)
            ->where('estatus_pago', 'pendiente')
            ->where('fecha_venta', '<=', $umbral)
            ->get();

        $canceladas = 0;

        foreach ($ventas as $venta) {
            try {
                $pago = $this->mercadoPago->buscarPagoAprobado($venta);
            } catch (\Throwable $e) {
                Log::warning('Cancelación de pendientes: no se pudo verificar el pago antes de cancelar; se pospone al siguiente ciclo.', [
                    'venta_id'  => $venta->id,
                    'exception' => $e,
                ]);

                continue;
            }

            if ($pago) {
                // El pago sí se aprobó pero ni el webhook ni el retorno del usuario ni
                // la reconciliación programada lo confirmaron todavía — se confirma
                // aquí en vez de cancelar, y se envía el correo igual que en
                // ReconciliarPagosPendientes para no dejar al comprador sin sus boletos.
                if ($this->compraService->confirmarPagoAprobado($venta)) {
                    $venta->refresh()->load(['usuario', 'accesos', 'detalles.boletoEvento.zonaTeatro', 'detalles.boletoEvento.evento.teatro']);

                    if ($venta->usuario?->correo) {
                        Mail::to($venta->usuario->correo)->queue(new ConfirmacionCompraMail($venta));
                    }
                }

                continue;
            }

            if ($this->compraService->cancelarVentaPendiente($venta)) {
                $canceladas++;
            }
        }

        return $canceladas;
    }
}
