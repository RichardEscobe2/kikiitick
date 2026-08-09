<?php

namespace App\Services;

use App\Exceptions\CompraException;
use App\Models\Acceso;
use App\Models\Asiento;
use App\Models\AsientoEvento;
use App\Models\BoletoEvento;
use App\Models\DetalleVenta;
use App\Models\Evento;
use App\Models\User;
use App\Models\Venta;
use App\Models\ZonaTeatro;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompraService
{
    /**
     * Duración estricta del bloqueo temporal de asientos (RN-05): 5 minutos / 300 segundos.
     */
    private const MINUTOS_RESERVA = 5;

    /**
     * Ventana de gracia para pagos en efectivo (OXXO/SPEI): a diferencia de tarjeta
     * (confirmación síncrona en el redirect de Mercado Pago), el pago en efectivo puede
     * tardar horas/días en pagarse en tienda — los asientos deben permanecer apartados
     * mientras tanto, no liberarse a los 5 minutos del bloqueo inicial de RN-05.
     */
    private const HORAS_RESERVA_EFECTIVO = 24;

    /**
     * Bloquea temporalmente los asientos seleccionados para el usuario.
     *
     * @throws CompraException
     */
    public function reservarAsientos(User $usuario, int $eventoId, array $asientoIds): array
    {
        $evento = Evento::find($eventoId);

        if (!$evento || $evento->estatus !== 'activo') {
            throw new CompraException('Este evento no está disponible para reservas en este momento.');
        }

        DB::transaction(function () use ($usuario, $eventoId, $asientoIds) {
            // 1. Limpiar reservas expiradas de este evento antes de validar
            AsientoEvento::where('evento_id', $eventoId)
                ->where('estado', 'reservado')
                ->where('reservado_hasta', '<', Carbon::now())
                ->update([
                    'estado'                   => 'disponible',
                    'reservado_por_usuario_id' => null,
                    'reservado_hasta'          => null,
                ]);

            // 2. Verificar si algún asiento ya está ocupado o reservado por OTRO usuario no expirado
            $ocupados = AsientoEvento::where('evento_id', $eventoId)
                ->whereIn('asiento_id', $asientoIds)
                ->where(function ($q) use ($usuario) {
                    $q->where('estado', 'vendido')
                      ->orWhere(function ($sub) use ($usuario) {
                          $sub->where('estado', 'reservado')
                              ->where('reservado_hasta', '>=', Carbon::now())
                              ->where('reservado_por_usuario_id', '!=', $usuario->id);
                      });
                })
                ->lockForUpdate()
                ->exists();

            if ($ocupados) {
                throw new CompraException('Uno o más asientos seleccionados ya fueron apartados o vendidos. Por favor selecciona otros.');
            }

            // 3. Bloquear asientos por el periodo estricto de reserva
            $expiraEn = Carbon::now()->addMinutes(self::MINUTOS_RESERVA);
            $now = Carbon::now();

            // ⚡ Bulk upsert en vez de updateOrCreate() por asiento: reduce cuánto tiempo
            // se mantienen abiertos los locks de esta transacción con carritos grandes.
            $filasParaReservar = array_map(fn ($asientoId) => [
                'evento_id'                => $eventoId,
                'asiento_id'                => $asientoId,
                'estado'                   => 'reservado',
                'reservado_por_usuario_id' => $usuario->id,
                'reservado_hasta'          => $expiraEn,
                'created_at'               => $now,
                'updated_at'               => $now,
            ], $asientoIds);

            AsientoEvento::upsert(
                $filasParaReservar,
                ['evento_id', 'asiento_id'],
                ['estado', 'reservado_por_usuario_id', 'reservado_hasta', 'updated_at']
            );
        }, attempts: 3);

        return [
            'message'           => 'Asientos reservados temporalmente por ' . self::MINUTOS_RESERVA . ' minutos.',
            'expira_en'         => Carbon::now()->addMinutes(self::MINUTOS_RESERVA)->toIso8601String(),
            'tiempo_limite_seg' => self::MINUTOS_RESERVA * 60,
        ];
    }

    /**
     * Procesa la orden de compra: valida la reserva vigente, calcula montos, crea la
     * Venta en estado 'pendiente' y emite los accesos/boletos (RN-05, RN-08, RN-11, RN-13).
     *
     * @throws CompraException
     */
    public function procesarCompra(User $usuario, int $eventoId, array $asientoIds, string $metodoPago = 'tarjeta'): Venta
    {
        return DB::transaction(function () use ($usuario, $eventoId, $asientoIds, $metodoPago) {
            $evento = Evento::findOrFail($eventoId);

            if ($evento->estatus !== 'activo') {
                throw new CompraException('Este evento no está disponible para procesar compras en este momento.');
            }

            // 🛡️ RN-08: lockForUpdate() evita que dos requests de checkout concurrentes
            // procesen la misma reserva dos veces.
            $asientosValidos = AsientoEvento::where('evento_id', $eventoId)
                ->whereIn('asiento_id', $asientoIds)
                ->where('reservado_por_usuario_id', $usuario->id)
                ->where('estado', 'reservado')
                ->where('reservado_hasta', '>=', Carbon::now())
                ->lockForUpdate()
                ->get();

            if ($asientosValidos->count() !== count($asientoIds)) {
                throw new CompraException('Tu reserva de asientos ha expirado o no es válida. Vuelve a intentarlo.');
            }

            [$montoNetoTotal, $detallesParaCrear, $boletosPorZona, $asientosPorZona] =
                $this->calcularDetalleCompra($eventoId, $asientoIds);

            $comisionPorBoleto = (float) $evento->comision_fija_empresa;
            $totalComisiones = count($asientoIds) * $comisionPorBoleto;
            $montoTotal = $montoNetoTotal + $totalComisiones;

            // 🛡️ RN-11: estado inicial 'pendiente' — el Módulo 5 lo confirma a 'pagado'
            // únicamente tras la respuesta real de la pasarela de pago.
            $nuevaVenta = Venta::create([
                'usuario_id'       => $usuario->id,
                'monto_neto'       => $montoNetoTotal,
                'total_comisiones' => $totalComisiones,
                'monto_total'      => $montoTotal,
                'estatus_pago'     => 'pendiente',
                'metodo_pago'      => $metodoPago,
                'fecha_venta'      => Carbon::now(),
            ]);

            // 🔗 Enlaza estos asientos con la Venta que los está comprando, para que la
            // confirmación de pago (webhook) pueda ubicar exactamente qué filas de
            // asientos_evento marcar 'vendido' sin reconstruirlas a partir de datos
            // denormalizados de Acceso.
            $actualizacionAsientos = ['venta_id' => $nuevaVenta->id];

            // 🛡️ Pago en efectivo (OXXO/SPEI): el bloqueo pesimista de 5 minutos de
            // reservarAsientos() (RN-05) alcanza para tarjeta (confirmación síncrona en
            // el redirect de Mercado Pago), pero NO para efectivo, cuyo pago real puede
            // tardar horas en tienda — sin esta extensión los asientos se liberarían
            // solos mucho antes de que el usuario llegue a pagar la ficha.
            if ($metodoPago === 'oxxo') {
                $actualizacionAsientos['reservado_hasta'] = Carbon::now()->addHours(self::HORAS_RESERVA_EFECTIVO);
            }

            AsientoEvento::where('evento_id', $eventoId)
                ->whereIn('asiento_id', $asientoIds)
                ->update($actualizacionAsientos);

            // ⚡ Bulk insert en vez de create() por detalle: reduce cuánto tiempo se
            // mantienen abiertos los locks de esta transacción (RN-08, lockForUpdate arriba).
            $now = Carbon::now();
            $filasDetalles = array_map(fn ($detalle) => [
                'venta_id'         => $nuevaVenta->id,
                'boleto_evento_id' => $detalle['boleto_evento_id'],
                'cantidad'         => $detalle['cantidad'],
                'subtotal'         => $detalle['subtotal'],
                'created_at'       => $now,
                'updated_at'       => $now,
            ], $detallesParaCrear);

            DetalleVenta::insert($filasDetalles);

            $this->emitirAccesos($nuevaVenta, $eventoId, $usuario, $asientosPorZona, $boletosPorZona);

            // 🛡️ RN-11/RN-16: los asientos permanecen en 'reservado' — NO se marcan 'vendido'
            // aquí. Esa transición corresponde al Módulo 5 cuando la pasarela de pago
            // confirme el cobro real. El temporizador de reserva (reservado_hasta) sigue
            // vigente como red de seguridad si el pago nunca se completa.

            return $nuevaVenta;
        }, attempts: 3);
    }

    /**
     * Confirma una Venta cuyo pago fue aprobado por Mercado Pago (llamado desde el
     * webhook, después de re-verificar el estado del pago contra la API de Mercado Pago
     * — nunca a partir del cuerpo de la notificación sin verificar).
     *
     * Idempotente: si la Venta ya estaba 'pagado' (notificación duplicada/reintento de
     * Mercado Pago), no hace nada y devuelve false para que el llamador no reenvíe el
     * correo de confirmación una segunda vez.
     *
     * @return bool true si esta llamada fue la que efectivamente confirmó el pago.
     */
    public function confirmarPagoAprobado(Venta $venta): bool
    {
        return DB::transaction(function () use ($venta) {
            // lockForUpdate: si Mercado Pago reintenta la misma notificación en paralelo,
            // solo una petición debe ganar la transición pendiente -> pagado.
            $ventaBloqueada = Venta::where('id', $venta->id)->lockForUpdate()->first();

            if (!$ventaBloqueada || $ventaBloqueada->estatus_pago === 'pagado') {
                return false;
            }

            // 🛡️ RN-01/RN-05: 'vendido' es un estado terminal — reservado_hasta se limpia
            // a null para que ningún cleanup de reservas expiradas (que solo mira
            // estado='reservado') pueda tocar jamás estos asientos.
            AsientoEvento::where('venta_id', $ventaBloqueada->id)
                ->update(['estado' => 'vendido', 'reservado_hasta' => null]);

            // metodo_pago ya quedó fijado en procesarCompra() ('tarjeta'/'oxxo', la
            // intención real del comprador) — no se sobreescribe aquí para no perder esa
            // distinción de cara a "Mis Boletos"/reportes.
            $ventaBloqueada->update([
                'estatus_pago' => 'pagado',
            ]);

            return true;
        }, attempts: 3);
    }

    /**
     * Venta directa en taquilla (RF-10/RN-09): el personal autorizado (vendedor,
     * organizador o admin) cobra en efectivo/tarjeta física y la venta queda
     * inmediatamente 'pagado', sin pasar por la pasarela de pago en línea.
     *
     * @throws CompraException
     */
    public function comprarEnTaquilla(User $vendedor, int $eventoId, array $asientoIds, string $metodoPago): Venta
    {
        return DB::transaction(function () use ($vendedor, $eventoId, $asientoIds, $metodoPago) {
            $evento = Evento::find($eventoId);

            if (!$evento || $evento->estatus !== 'activo') {
                throw new CompraException('Este evento no está disponible para venta en taquilla.');
            }

            // Libera reservas online expiradas antes de validar disponibilidad, igual
            // que en reservarAsientos().
            AsientoEvento::where('evento_id', $eventoId)
                ->where('estado', 'reservado')
                ->where('reservado_hasta', '<', Carbon::now())
                ->update(['estado' => 'disponible', 'reservado_por_usuario_id' => null, 'reservado_hasta' => null]);

            $ocupados = AsientoEvento::where('evento_id', $eventoId)
                ->whereIn('asiento_id', $asientoIds)
                ->whereIn('estado', ['vendido', 'reservado'])
                ->lockForUpdate()
                ->exists();

            if ($ocupados) {
                throw new CompraException('Uno o más asientos seleccionados ya están ocupados o reservados.');
            }

            [$montoNetoTotal, $detallesParaCrear, $boletosPorZona, $asientosPorZona] =
                $this->calcularDetalleCompra($eventoId, $asientoIds);

            $comisionPorBoleto = (float) $evento->comision_fija_empresa;
            $totalComisiones = count($asientoIds) * $comisionPorBoleto;
            $montoTotal = $montoNetoTotal + $totalComisiones;

            $nuevaVenta = Venta::create([
                'usuario_id'             => $vendedor->id,
                'vendido_por_usuario_id' => $vendedor->id,
                'monto_neto'             => $montoNetoTotal,
                'total_comisiones'       => $totalComisiones,
                'monto_total'            => $montoTotal,
                'estatus_pago'           => 'pagado',
                'metodo_pago'            => $metodoPago,
                'fecha_venta'            => Carbon::now(),
            ]);

            $now = Carbon::now();
            $filasDetalles = array_map(fn ($detalle) => [
                'venta_id'         => $nuevaVenta->id,
                'boleto_evento_id' => $detalle['boleto_evento_id'],
                'cantidad'         => $detalle['cantidad'],
                'subtotal'         => $detalle['subtotal'],
                'created_at'       => $now,
                'updated_at'       => $now,
            ], $detallesParaCrear);
            DetalleVenta::insert($filasDetalles);

            $this->emitirAccesos($nuevaVenta, $eventoId, $vendedor, $asientosPorZona, $boletosPorZona);

            // Venta de taquilla es inmediata: marca 'vendido' directamente (sin pasar por
            // el estado intermedio 'reservado' del flujo de checkout en línea).
            $filasVendidas = array_map(fn ($asientoId) => [
                'evento_id'                => $eventoId,
                'asiento_id'                => $asientoId,
                'estado'                   => 'vendido',
                'reservado_por_usuario_id' => $vendedor->id,
                'reservado_hasta'          => null,
                'venta_id'                 => $nuevaVenta->id,
                'created_at'               => $now,
                'updated_at'               => $now,
            ], $asientoIds);

            AsientoEvento::upsert(
                $filasVendidas,
                ['evento_id', 'asiento_id'],
                ['estado', 'reservado_por_usuario_id', 'reservado_hasta', 'venta_id', 'updated_at']
            );

            return $nuevaVenta;
        }, attempts: 3);
    }

    /**
     * Agrupa los asientos por zona, valida tarifas y calcula el monto neto.
     *
     * @throws CompraException si alguna zona no tiene tarifa configurada.
     */
    private function calcularDetalleCompra(int $eventoId, array $asientoIds): array
    {
        $asientosFisicos = Asiento::whereIn('id', $asientoIds)->get();
        $asientosPorZona = $asientosFisicos->groupBy('zona_teatro_id');

        $montoNetoTotal = 0;
        $detallesParaCrear = [];
        $boletosPorZona = [];

        foreach ($asientosPorZona as $zonaId => $asientosGrupo) {
            $cantidad = $asientosGrupo->count();
            $boletoEvento = BoletoEvento::where('evento_id', $eventoId)
                ->where('zona_teatro_id', $zonaId)
                ->first();

            // 🛡️ Evita el crash "No query results for model [BoletoEvento]" cuando el
            // organizador aún no configuró tarifa para esta zona. No se asume $0.00 aquí
            // (eso permitiría comprar boletos gratis) — se rechaza la compra con un
            // mensaje claro en vez de una excepción cruda de framework.
            if (!$boletoEvento) {
                throw new CompraException('Uno o más asientos seleccionados pertenecen a una zona sin tarifa configurada. Contacta al organizador o intenta con otros asientos.');
            }

            $subtotalZona = $boletoEvento->precio_base * $cantidad;
            $montoNetoTotal += $subtotalZona;

            // Reducir stock disponible en la zona
            $boletoEvento->decrement('stock_disponible', $cantidad);

            $detallesParaCrear[] = [
                'boleto_evento_id' => $boletoEvento->id,
                'cantidad'         => $cantidad,
                'subtotal'         => $subtotalZona,
            ];

            $boletosPorZona[$zonaId] = $boletoEvento;
        }

        return [$montoNetoTotal, $detallesParaCrear, $boletosPorZona, $asientosPorZona];
    }

    /**
     * Emite un acceso/boleto individual (hash HMAC + QR) por cada asiento comprado,
     * en estado 'pendiente' hasta que el Módulo 5 confirme el pago (RN-05).
     */
    private function emitirAccesos(Venta $venta, int $eventoId, User $usuario, $asientosPorZona, array $boletosPorZona): void
    {
        foreach ($asientosPorZona as $zonaId => $asientosGrupo) {
            $boletoEvento = $boletosPorZona[$zonaId];
            $zona = ZonaTeatro::find($zonaId);

            foreach ($asientosGrupo as $asientoFisico) {
                $payload = implode('|', [$venta->id, $boletoEvento->id, $asientoFisico->id, $usuario->id]);

                Acceso::create([
                    'venta_id'         => $venta->id,
                    'boleto_evento_id' => $boletoEvento->id,
                    'clave_evento'     => (string) $eventoId,
                    'numero_control'   => strtoupper(Str::random(10)),
                    'hash_seguridad'   => hash_hmac('sha256', $payload, config('app.key')),
                    'token_qr'         => (string) Str::uuid(),
                    'seccion_pasillo'  => $zona ? $zona->nombre_zona : 'General',
                    'fila_palco'       => $asientoFisico->fila,
                    'numero_asiento'   => (string) $asientoFisico->numero,
                    'estatus'          => 'pendiente',
                ]);
            }
        }
    }
}
