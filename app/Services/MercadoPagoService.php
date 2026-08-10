<?php

namespace App\Services;

use App\Models\Venta;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPSearchRequest;
use MercadoPago\Resources\Payment;

/**
 * Encapsula toda la integración con el SDK oficial de Mercado Pago (mercadopago/dx-php ^3.13).
 * No contiene lógica de negocio de KikiiTick (eso vive en CompraService) — solo traduce
 * entre nuestros modelos y las llamadas a la API de Mercado Pago.
 */
class MercadoPagoService
{
    public function __construct()
    {
        MercadoPagoConfig::setAccessToken((string) config('services.mercadopago.access_token'));
    }

    /**
     * Crea una preferencia de pago para una Venta ya existente (debe estar en estado
     * 'pendiente'; RN-11). El `external_reference` se fija al id de la Venta para poder
     * correlacionar la notificación del webhook de vuelta a nuestro registro.
     *
     * @param  array<int, array{titulo: string, cantidad: int, precio_unitario: float}>  $boletos
     * @return array{id: string, init_point: string}
     *
     * @throws MPApiException
     */
    public function crearPreferencia(Venta $venta, array $boletos): array
    {
        $items = array_map(fn (array $boleto) => [
            'title'       => $boleto['titulo'],
            'quantity'    => (int) $boleto['cantidad'],
            'unit_price'  => (float) $boleto['precio_unitario'],
            'currency_id' => 'MXN',
        ], $boletos);

        // Cargo por servicio como línea de item aparte, para que el desglose en el
        // checkout de Mercado Pago coincida con el que ve el usuario en KikiiTick.
        if ((float) $venta->total_comisiones > 0) {
            $items[] = [
                'title'       => 'Cargo por servicio KikiiTick',
                'quantity'    => 1,
                'unit_price'  => (float) $venta->total_comisiones,
                'currency_id' => 'MXN',
            ];
        }

        // 🐛 Encontrado junto con el mismo bug en ConfirmacionCompraMail::urlConfirmacion():
        // FRONTEND_URL en .env vivía sin esquema ("dominio.com" en vez de
        // "https://dominio.com"). Sin esta guarda, back_urls.success/pending/failure
        // se enviaban a Mercado Pago SIN esquema — su API las trata como URL inválida
        // (o, en el mejor caso, ambiguo), rompiendo el redirect real de pago, no solo
        // el enlace del correo. El valor real de .env ya se corrigió, esta
        // normalización queda como defensa ante una futura mala configuración.
        $frontendUrl = trim((string) config('app.frontend_url'));
        if ($frontendUrl !== '' && !preg_match('#^https?://#i', $frontendUrl)) {
            $frontendUrl = 'https://' . $frontendUrl;
        }
        $frontendUrl = rtrim($frontendUrl, '/');

        // 🛡️ Las 3 back_urls apuntan a la misma ConfirmacionCompra.vue: el estado real
        // del pago (pendiente/pagado/fallido) se re-consulta ahí contra NUESTRO backend
        // (GET /api/ventas/{id}), nunca se confía en a qué back_url redirigió Mercado
        // Pago como fuente de verdad del resultado del pago.
        $urlConfirmacion = "{$frontendUrl}/confirmacion/{$venta->id}";

        $request = [
            'items'              => $items,
            'external_reference' => (string) $venta->id,
            'back_urls'          => [
                'success' => $urlConfirmacion,
                'pending' => $urlConfirmacion,
                'failure' => $urlConfirmacion,
            ],
        ];

        // 🛡️ auto_return solo es válido cuando back_urls.success es una URL pública
        // alcanzable por Mercado Pago — con un FRONTEND_URL de localhost (cualquier
        // entorno de desarrollo), la API rechaza la preferencia COMPLETA con 400
        // "auto_return invalid. back_url.success must be defined" incluso teniendo
        // success correctamente definido. Verificado de forma aislada: la misma
        // petición funciona sin auto_return, y funciona con auto_return si success es
        // una URL https pública — el problema es exclusivamente la combinación
        // auto_return + localhost. Sin auto_return, el usuario simplemente no es
        // redirigido solo automáticamente al pagar (debe hacer clic para volver); no
        // afecta la confirmación real del pago, que siempre se verifica vía webhook +
        // GET /api/ventas/{id}, nunca por a qué back_url redirigió Mercado Pago.
        $esUrlPublica = !str_contains($frontendUrl, 'localhost') && !str_contains($frontendUrl, '127.0.0.1');
        if ($esUrlPublica) {
            $request['auto_return'] = 'approved';
        }

        // 🛡️ Mismo problema que auto_return, pero con notification_url: Mercado Pago
        // rechaza la preferencia COMPLETA con 400 "notificaction_url attribute must be
        // a valid url" cuando esa URL no es públicamente alcanzable (localhost/127.0.0.1
        // en cualquier entorno de desarrollo sin túnel). Verificado en logs (venta_id 15
        // y 16). En local simplemente omitimos notification_url — el pago sigue
        // procesable manualmente vía GET /api/ventas/{id} contra la API de Mercado Pago;
        // en producción, con una APP_URL pública, sí se envía y el webhook confirma el
        // pago automáticamente (RN-11).
        $backendUrl = url('/api/pagos/webhook');
        $backendEsPublica = !str_contains($backendUrl, 'localhost') && !str_contains($backendUrl, '127.0.0.1');
        if ($backendEsPublica) {
            $request['notification_url'] = $backendUrl;
        }

        // 🛡️ El comprador ya eligió "efectivo" en EventoCheckout.vue (Venta::metodo_pago
        // fijado en CompraService::procesarCompra() antes de llegar aquí) — sin esto,
        // Checkout Pro muestra tarjeta primero por defecto y el usuario tendría que
        // buscar OXXO manualmente entre las demás opciones. default_payment_method_id
        // preselecciona OXXO, y excluded_payment_types oculta tarjeta de crédito/débito
        // para no confundir con un método que el usuario ya descartó explícitamente.
        if (in_array($venta->metodo_pago, ['oxxo', 'efectivo'], true)) {
            $request['payment_methods'] = [
                'default_payment_method_id' => 'oxxo',
                'excluded_payment_types'    => [
                    ['id' => 'credit_card'],
                    ['id' => 'debit_card'],
                ],
            ];
        }

        $preference = (new PreferenceClient())->create($request);

        return [
            'id'         => (string) $preference->id,
            'init_point' => (string) $preference->init_point,
        ];
    }

    /**
     * Consulta el estado actual y autoritativo de un pago directamente en la API de
     * Mercado Pago. Nunca se debe confiar en el campo "status" de una notificación de
     * webhook sin esta verificación server-to-server (las notificaciones son solo un
     * aviso ligero de que algo cambió, no la fuente de verdad del estado del pago).
     *
     * @throws MPApiException
     */
    public function obtenerPago(int $paymentId): Payment
    {
        return (new PaymentClient())->get($paymentId);
    }

    /**
     * Busca en la API de Mercado Pago si existe un pago aprobado para esta Venta,
     * sin depender de un payment_id conocido de antemano (a diferencia de
     * obtenerPago()). Usado por el comando de reconciliación programada
     * (kikiitick:reconciliar-pagos-pendientes) como red de seguridad cuando ni el
     * webhook ni el retorno del usuario a ConfirmacionCompra.vue llegaron a
     * confirmar el pago.
     *
     * @throws MPApiException
     */
    public function buscarPagoAprobado(Venta $venta): ?Payment
    {
        $resultado = (new PaymentClient())->search(new MPSearchRequest(10, 0, [
            'external_reference' => (string) $venta->id,
            'sort'               => 'date_created',
            'criteria'           => 'desc',
        ]));

        foreach ($resultado->results as $pago) {
            // 🛡️ Mismo chequeo BOLA que reconciliarPagoPendiente(): el filtro de
            // búsqueda ya restringe por external_reference, pero se re-verifica aquí
            // por si la API alguna vez devolviera resultados de otra referencia.
            if ($pago->status === 'approved' && (string) $pago->external_reference === (string) $venta->id) {
                return $pago;
            }
        }

        return null;
    }
}
