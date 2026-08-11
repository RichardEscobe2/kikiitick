<?php

namespace App\Http\Controllers;

use App\Exceptions\CompraException;
use App\Mail\ConfirmacionCompraMail;
use App\Models\Venta;
use App\Services\CompraService;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use MercadoPago\Exceptions\InvalidWebhookSignatureException;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Webhook\WebhookSignatureValidator;

class CompraController extends Controller
{
    public function __construct(
        private CompraService $compraService,
        private MercadoPagoService $mercadoPago,
    ) {
    }

    /**
     * GET /api/ventas/{id}
     * Detalle de una orden para las vistas de confirmación/ficha del frontend
     * (ConfirmacionCompra.vue, FichaOxxo.vue). Autorización por propiedad (BOLA):
     * solo el comprador o un admin pueden consultarla.
     */
    public function mostrarVenta(Request $request, $id)
    {
        $venta = Venta::with([
            'accesos',
            'detalles.boletoEvento.zonaTeatro',
            'detalles.boletoEvento.evento.teatro',
        ])->find($id);

        if (!$venta) {
            return response()->json(['message' => 'Orden no encontrada.'], 404);
        }

        $user = $request->user();
        if ($venta->usuario_id !== $user->id && $user->rol !== 'admin') {
            return response()->json(['message' => 'No tienes permiso para ver esta orden.'], 403);
        }

        // 🛡️ Reconciliación de respaldo (RN-11): cuando Mercado Pago redirige de
        // vuelta a ConfirmacionCompra.vue, añade un payment_id/collection_id real a la
        // URL. Si el webhook aún no llegó/lo rechazó (ej. MERCADOPAGO_WEBHOOK_SECRET
        // sin configurar) y la venta sigue 'pendiente', usamos ese id ÚNICAMENTE como
        // puntero para re-consultar el pago vía la API de Mercado Pago — nunca se
        // confía en el parámetro "status"/"collection_status" de la query string en sí
        // (viene del navegador, controlado por el usuario). Mismo patrón de
        // verificación server-to-server que webhookMercadoPago().
        if ($venta->estatus_pago === 'pendiente') {
            $paymentId = $request->query('payment_id') ?? $request->query('collection_id');

            if ($paymentId) {
                $this->reconciliarPagoPendiente($venta, (int) $paymentId);
                $venta->refresh();
            }
        }

        $primerDetalle = $venta->detalles->first();
        $evento = $primerDetalle?->boletoEvento?->evento;

        return response()->json([
            'id'               => $venta->id,
            'folio'            => 'KT-' . str_pad((string) $venta->id, 6, '0', STR_PAD_LEFT),
            'estatus_pago'     => $venta->estatus_pago,
            'metodo_pago'      => $venta->metodo_pago,
            'monto_neto'       => (float) $venta->monto_neto,
            'total_comisiones' => (float) $venta->total_comisiones,
            'monto_total'      => (float) $venta->monto_total,
            'fecha_venta'      => $venta->fecha_venta,
            // 🛡️ Autoritativo desde el backend (no un flag de build del frontend): le
            // dice a ConfirmacionCompra.vue si puede ofrecer el botón de simulación de
            // pago (POST /api/ventas/{id}/simular-pago), que el propio endpoint también
            // vuelve a verificar server-side antes de hacer nada.
            'entorno_pruebas'  => !app()->environment('production'),
            'evento'           => $evento ? [
                'id'         => $evento->id,
                'titulo'     => $evento->titulo,
                'imagen_url' => $evento->imagen_url,
                'fecha_hora' => $evento->fecha_hora,
                'teatro'     => [
                    'nombre'    => $evento->teatro->nombre ?? '',
                    'ubicacion' => $evento->teatro->ubicacion ?? '',
                ],
            ] : null,
            'boletos' => $venta->accesos->map(fn ($acceso) => [
                'numero_control'  => $acceso->numero_control,
                'token_qr'        => $acceso->token_qr,
                'seccion_pasillo' => $acceso->seccion_pasillo,
                'fila_palco'      => $acceso->fila_palco,
                'numero_asiento'  => $acceso->numero_asiento,
                'estatus'         => $acceso->estatus,
            ]),
        ], 200);
    }

    /**
     * POST /api/ventas/{id}/simular-pago
     * SOLO fuera de producción: aprueba manualmente un pago pendiente sin depender del
     * sandbox real de Mercado Pago — útil para probar el flujo de OXXO, cuyo pago real
     * en tienda puede tardar horas y no se puede disparar bajo demanda en QA. Nunca
     * disponible en producción (verificado aquí en el servidor, no solo ocultando el
     * botón en el frontend) y reutiliza confirmarPagoAprobado(), el mismo camino que
     * usa el webhook real, para no duplicar la lógica de transición de estado (RN-11).
     */
    public function simularPago(Request $request, $id)
    {
        if (app()->environment('production')) {
            // 404 en vez de 403: no revela ni siquiera que la ruta existe en producción.
            abort(404);
        }

        $venta = Venta::find($id);

        if (!$venta) {
            return response()->json(['message' => 'Orden no encontrada.'], 404);
        }

        // 🛡️ BOLA: misma autorización por propiedad que mostrarVenta() — sin esto,
        // cualquier usuario autenticado podría "pagar" órdenes ajenas con solo
        // adivinar el id, incluso en un entorno de pruebas.
        $user = $request->user();
        if ($venta->usuario_id !== $user->id && $user->rol !== 'admin') {
            return response()->json(['message' => 'No tienes permiso para simular esta orden.'], 403);
        }

        if ($venta->estatus_pago !== 'pendiente') {
            return response()->json(['message' => 'Esta orden ya no está pendiente de pago.'], 422);
        }

        $seConfirmoAhora = $this->compraService->confirmarPagoAprobado($venta);

        if ($seConfirmoAhora) {
            $venta->refresh()->load(['usuario', 'accesos', 'detalles.boletoEvento.zonaTeatro', 'detalles.boletoEvento.evento.teatro']);

            if ($venta->usuario?->correo) {
                Mail::to($venta->usuario->correo)->queue(new ConfirmacionCompraMail($venta));
            }
        }

        return response()->json([
            'message'      => 'Pago simulado y confirmado (solo entorno de pruebas).',
            'estatus_pago' => $venta->fresh()->estatus_pago,
        ], 200);
    }

    /**
     * Re-verifica un pago pendiente directamente contra la API de Mercado Pago
     * (nunca contra datos de la query string) y, solo si está genuinamente aprobado
     * Y pertenece exactamente a esta Venta (external_reference), lo confirma con la
     * misma ruta que usa el webhook. Cualquier fallo se ignora silenciosamente — el
     * webhook y el polling del frontend siguen siendo la vía normal de confirmación;
     * esto es solo un atajo de UX cuando el usuario ya volvió del checkout.
     */
    private function reconciliarPagoPendiente(Venta $venta, int $paymentId): void
    {
        try {
            $pago = $this->mercadoPago->obtenerPago($paymentId);
        } catch (\Throwable $e) {
            Log::warning('No se pudo reconciliar el pago pendiente al consultar la orden.', [
                'venta_id'   => $venta->id,
                'payment_id' => $paymentId,
                'exception'  => $e,
            ]);

            return;
        }

        // 🛡️ BOLA: el payment_id de la URL debe corresponder exactamente a esta
        // venta — si no coincide (id ajeno, manipulado, o de otra orden), se ignora.
        if ((string) $pago->external_reference !== (string) $venta->id) {
            return;
        }

        if ($pago->status !== 'approved') {
            return;
        }

        $seConfirmoAhora = $this->compraService->confirmarPagoAprobado($venta);

        if ($seConfirmoAhora) {
            $venta->refresh()->load(['usuario', 'accesos', 'detalles.boletoEvento.zonaTeatro', 'detalles.boletoEvento.evento.teatro']);

            if ($venta->usuario?->correo) {
                Mail::to($venta->usuario->correo)->queue(new ConfirmacionCompraMail($venta));
            }
        }
    }

    /**
     * GET /api/mis-boletos
     * Historial de compras completadas ('pagado') del usuario autenticado, para la
     * vista "Mis Boletos". Ventas 'pendiente'/'fallido' NO aparecen aquí a propósito —
     * un boleto solo es válido/visible una vez que el pago fue confirmado de verdad
     * (RN-11), igual que el correo de confirmación solo se despacha en ese momento.
     */
    public function misBoletos(Request $request)
    {
        $ventas = Venta::with([
            'accesos',
            'detalles.boletoEvento.zonaTeatro',
            'detalles.boletoEvento.evento.teatro',
        ])
            ->where('usuario_id', $request->user()->id)
            ->where('estatus_pago', 'pagado')
            ->orderByDesc('fecha_venta')
            ->get();

        return response()->json($ventas->map(function ($venta) {
            $primerDetalle = $venta->detalles->first();
            $evento = $primerDetalle?->boletoEvento?->evento;

            return [
                'id'          => $venta->id,
                'folio'       => 'KT-' . str_pad((string) $venta->id, 6, '0', STR_PAD_LEFT),
                'metodo_pago' => $venta->metodo_pago,
                'monto_total' => (float) $venta->monto_total,
                'fecha_venta' => $venta->fecha_venta,
                'evento'      => $evento ? [
                    'id'         => $evento->id,
                    'titulo'     => $evento->titulo,
                    'imagen_url' => $evento->imagen_url,
                    'fecha_hora' => $evento->fecha_hora,
                    'teatro'     => [
                        'nombre'    => $evento->teatro->nombre ?? '',
                        'ubicacion' => $evento->teatro->ubicacion ?? '',
                    ],
                ] : null,
                'boletos' => $venta->accesos->map(fn ($acceso) => [
                    'numero_control'  => $acceso->numero_control,
                    'token_qr'        => $acceso->token_qr,
                    'seccion_pasillo' => $acceso->seccion_pasillo,
                    'fila_palco'      => $acceso->fila_palco,
                    'numero_asiento'  => $acceso->numero_asiento,
                    'estatus'         => $acceso->estatus,
                ]),
            ];
        }), 200);
    }

    /**
     * POST /api/boletos/reservar
     * Bloquear asientos de forma temporal por 5 minutos (RN-05)
     */
    public function reservarAsientos(Request $request)
    {
        $validated = $request->validate([
            'evento_id'      => 'required|exists:eventos,id',
            'asiento_ids'    => 'required|array|min:1',
            'asiento_ids.*'  => 'exists:asientos,id',
        ]);

        try {
            $resultado = $this->compraService->reservarAsientos(
                $request->user(),
                $validated['evento_id'],
                $validated['asiento_ids']
            );

            return response()->json($resultado, 200);
        } catch (CompraException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Error inesperado al reservar asientos: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al procesar tu solicitud. Intenta nuevamente.'
            ], 500);
        }
    }

    /**
     * POST /api/boletos/comprar
     * Procesar la orden de compra: crea la Venta en estado 'pendiente', emite los
     * accesos, y genera la preferencia de pago de Mercado Pago para que el frontend
     * redirija al usuario al checkout (init_point).
     */
    public function procesarCompra(Request $request)
    {
        $validated = $request->validate([
            'evento_id'      => 'required|exists:eventos,id',
            'asiento_ids'    => 'required|array|min:1',
            'asiento_ids.*'  => 'exists:asientos,id',
            'metodo_pago'    => 'nullable|in:tarjeta,oxxo',
        ]);

        try {
            $venta = $this->compraService->procesarCompra(
                $request->user(),
                $validated['evento_id'],
                $validated['asiento_ids'],
                $validated['metodo_pago'] ?? 'tarjeta'
            );
        } catch (CompraException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Error inesperado al procesar la compra: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al procesar tu compra. Intenta nuevamente.'
            ], 500);
        }

        $respuestaBase = [
            'venta_id'     => $venta->id,
            'estatus_pago' => $venta->estatus_pago,
            'total'        => (float) $venta->monto_total,
        ];

        try {
            $venta->load(['detalles.boletoEvento.zonaTeatro', 'detalles.boletoEvento.evento']);

            $boletos = $venta->detalles->map(function ($detalle) {
                $zona = $detalle->boletoEvento->zonaTeatro;
                $evento = $detalle->boletoEvento->evento;

                return [
                    'titulo'          => trim(($evento->titulo ?? 'Boleto') . ' - ' . ($zona->nombre_zona ?? 'General')),
                    'cantidad'        => $detalle->cantidad,
                    'precio_unitario' => (float) $detalle->boletoEvento->precio_base,
                ];
            })->all();

            $preferencia = $this->mercadoPago->crearPreferencia($venta, $boletos);
        } catch (MPApiException $e) {
            // 🛡️ La Venta ya existe y los asientos siguen bloqueados por el temporizador
            // de reserva — no se pierde nada, el usuario puede reintentar el pago.
            // Se registra el status code y el cuerpo real de la respuesta de Mercado
            // Pago (getMessage() por sí solo es un genérico "Api error. Check response
            // for details" que no sirve para diagnosticar nada sin esto).
            Log::error('Error al crear preferencia de Mercado Pago: ' . $e->getMessage(), [
                'venta_id'         => $venta->id,
                'mp_status_code'   => $e->getStatusCode(),
                'mp_response_body' => $e->getApiResponse()->getContent(),
            ]);

            return response()->json(array_merge($respuestaBase, [
                'message' => 'Tu orden fue creada, pero no se pudo iniciar el pago en línea. Intenta de nuevo en unos segundos.',
            ]), 502);
        } catch (\Throwable $e) {
            Log::error('Error al crear preferencia de Mercado Pago: ' . $e->getMessage(), ['exception' => $e, 'venta_id' => $venta->id]);

            return response()->json(array_merge($respuestaBase, [
                'message' => 'Tu orden fue creada, pero no se pudo iniciar el pago en línea. Intenta de nuevo en unos segundos.',
            ]), 502);
        }

        return response()->json(array_merge($respuestaBase, [
            'message'       => 'Orden creada, redirigiendo a Mercado Pago.',
            'preference_id' => $preferencia['id'],
            'init_point'    => $preferencia['init_point'],
        ]), 201);
    }

    /**
     * POST /api/pagos/webhook
     * Recibe notificaciones de pago de Mercado Pago (ruta pública, sin sesión Sanctum
     * ni CSRF). Verifica la firma HMAC del encabezado x-signature antes de confiar en
     * CUALQUIER dato del payload (OWASP: nunca procesar webhooks sin autenticar su
     * origen), y re-consulta el estado real del pago en la API de Mercado Pago en vez
     * de confiar en el campo "status" del cuerpo de la notificación.
     */
    public function webhookMercadoPago(Request $request)
    {
        $secret = (string) config('services.mercadopago.webhook_secret');

        // 🛡️ PHP convierte los puntos de las claves de query string en guiones bajos
        // (?data.id=123 llega como $_GET['data_id']), y Mercado Pago también soporta el
        // formato legado (?id=123&topic=payment) — se revisan todas las variantes.
        $dataId = $request->query('data.id')
            ?? $request->query('data_id')
            ?? $request->query('id')
            ?? data_get($request->all(), 'data.id');

        try {
            WebhookSignatureValidator::validate(
                $request->header('x-signature'),
                $request->header('x-request-id'),
                $dataId ? (string) $dataId : null,
                $secret,
            );
        } catch (InvalidWebhookSignatureException $e) {
            Log::warning('Firma de webhook de Mercado Pago inválida — notificación descartada.', [
                'reason'     => $e->getMessage(),
                'request_id' => $request->header('x-request-id'),
            ]);

            return response()->json(['message' => 'Firma inválida.'], 401);
        } catch (\Throwable $e) {
            // Secreto no configurado u otro error de validación: no procesar, pero no
            // filtrar detalles internos en la respuesta.
            Log::error('Error validando la firma del webhook de Mercado Pago: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'No se pudo validar la notificación.'], 401);
        }

        // Ignorar notificaciones que no sean de pagos (ej. merchant_order) o sin id de pago.
        $tipoNotificacion = $request->input('type') ?? $request->query('topic');

        if (!$dataId || ($tipoNotificacion && $tipoNotificacion !== 'payment')) {
            return response()->json(['received' => true], 200);
        }

        try {
            $pago = $this->mercadoPago->obtenerPago((int) $dataId);
        } catch (\Throwable $e) {
            Log::error('No se pudo consultar el pago en la API de Mercado Pago.', [
                'payment_id' => $dataId,
                'exception'  => $e,
            ]);

            // 200 para que Mercado Pago no reintente indefinidamente por un fallo
            // transitorio de nuestro lado; el incidente queda registrado en logs.
            return response()->json(['received' => true], 200);
        }

        if ($pago->status === 'approved' && $pago->external_reference) {
            $venta = Venta::find((int) $pago->external_reference);

            if ($venta) {
                $seConfirmoAhora = $this->compraService->confirmarPagoAprobado($venta);

                // El correo de confirmación se despacha DESPUÉS de la transacción (nunca
                // dentro), y solo si esta llamada fue la que realmente confirmó el pago
                // (evita reenviarlo ante notificaciones duplicadas de Mercado Pago).
                if ($seConfirmoAhora) {
                    $venta->refresh()->load(['usuario', 'accesos', 'detalles.boletoEvento.zonaTeatro', 'detalles.boletoEvento.evento.teatro']);

                    if ($venta->usuario?->correo) {
                        Mail::to($venta->usuario->correo)->queue(new ConfirmacionCompraMail($venta));
                    }
                }
            }
        }

        return response()->json(['received' => true], 200);
    }

    /**
     * POST /api/boletos/comprar-pos
     * Venta directa en taquilla física (RF-10/RN-09), accesible a personal de taquilla
     * ('vendedor'), organizadores y administradores. Cobra en efectivo/tarjeta física y
     * confirma la venta de inmediato, sin pasar por Mercado Pago.
     */
    public function comprarPos(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->rol, ['vendedor', 'organizador', 'admin'], true)) {
            return response()->json([
                'message' => 'Acceso denegado. Se requiere una cuenta de taquilla, organizador o administrador.'
            ], 403);
        }

        $validated = $request->validate([
            'evento_id'      => 'required|exists:eventos,id',
            'asiento_ids'    => 'required|array|min:1',
            'asiento_ids.*'  => 'exists:asientos,id',
            'metodo_pago'    => 'required|in:efectivo,tarjeta_fisica',
            // 🛡️ El comprador de mostrador no tiene cuenta ni perfil en KikiiTick (la
            // Venta se crea con usuario_id = $vendedor->id, no el cliente) — el correo
            // de confirmación es su ÚNICA forma de recibir el folio/detalle de sus
            // boletos fuera del recibo físico impreso en el momento, así que ya no es
            // opcional.
            'cliente_email'  => 'required|email',
        ]);

        try {
            $venta = $this->compraService->comprarEnTaquilla(
                $user,
                $validated['evento_id'],
                $validated['asiento_ids'],
                $validated['metodo_pago']
            );

            $venta->load(['accesos', 'detalles.boletoEvento.zonaTeatro', 'detalles.boletoEvento.evento.teatro']);

            Log::channel('auditoria')->info('AUDITORIA_VENTA_POS: Venta en taquilla realizada', [
                'venta_id'           => $venta->id,
                'vendedor_usuario_id' => $user->id,
                'cliente_email'      => $validated['cliente_email'],
                'metodo_pago'        => $venta->metodo_pago,
                'monto_total'        => (float) $venta->monto_total,
                'asiento_ids'        => $validated['asiento_ids'],
            ]);

            // 📧 Envío SÍNCRONO (no encolado): la venta de taquilla ya nace 'pagado'
            // (RN-09) y el cliente está esperando en el mostrador — no puede depender
            // de que un worker de colas (`queue:work`) esté corriendo en ese momento,
            // a diferencia del correo de confirmación en línea que sí puede esperar.
            // Un fallo de envío se registra pero NO revierte la venta ya cobrada: el
            // recibo impreso en el momento (POSTaquilla.vue) sigue siendo válido.
            try {
                Mail::to($validated['cliente_email'])->send(new ConfirmacionCompraMail($venta));
            } catch (\Throwable $e) {
                Log::error('Error al enviar el recibo de venta de taquilla: ' . $e->getMessage(), [
                    'venta_id'  => $venta->id,
                    'exception' => $e,
                ]);
            }

            return response()->json([
                'message'      => 'Venta de taquilla registrada exitosamente.',
                'venta_id'     => $venta->id,
                'folio'        => 'KT-' . str_pad((string) $venta->id, 6, '0', STR_PAD_LEFT),
                'estatus_pago' => $venta->estatus_pago,
                'metodo_pago'  => $venta->metodo_pago,
                'total'        => (float) $venta->monto_total,
                'monto_neto'       => (float) $venta->monto_neto,
                'total_comisiones' => (float) $venta->total_comisiones,
                'fecha_venta'  => $venta->fecha_venta,
                'boletos'      => $venta->accesos->map(fn ($acceso) => [
                    'numero_control'  => $acceso->numero_control,
                    'seccion_pasillo' => $acceso->seccion_pasillo,
                    'fila_palco'      => $acceso->fila_palco,
                    'numero_asiento'  => $acceso->numero_asiento,
                ]),
            ], 201);
        } catch (CompraException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Error inesperado en venta de taquilla: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al procesar la venta.'
            ], 500);
        }
    }
}
