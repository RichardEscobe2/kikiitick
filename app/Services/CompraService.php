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

            foreach ($asientoIds as $asientoId) {
                AsientoEvento::updateOrCreate(
                    [
                        'evento_id'  => $eventoId,
                        'asiento_id' => $asientoId,
                    ],
                    [
                        'estado'                   => 'reservado',
                        'reservado_por_usuario_id' => $usuario->id,
                        'reservado_hasta'          => $expiraEn,
                    ]
                );
            }
        });

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
    public function procesarCompra(User $usuario, int $eventoId, array $asientoIds): Venta
    {
        return DB::transaction(function () use ($usuario, $eventoId, $asientoIds) {
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
                'fecha_venta'      => Carbon::now(),
            ]);

            foreach ($detallesParaCrear as $detalle) {
                DetalleVenta::create([
                    'venta_id'         => $nuevaVenta->id,
                    'boleto_evento_id' => $detalle['boleto_evento_id'],
                    'cantidad'         => $detalle['cantidad'],
                    'subtotal'         => $detalle['subtotal'],
                ]);
            }

            $this->emitirAccesos($nuevaVenta, $eventoId, $usuario, $asientosPorZona, $boletosPorZona);

            // 🛡️ RN-11/RN-16: los asientos permanecen en 'reservado' — NO se marcan 'vendido'
            // aquí. Esa transición corresponde al Módulo 5 cuando la pasarela de pago
            // confirme el cobro real. El temporizador de reserva (reservado_hasta) sigue
            // vigente como red de seguridad si el pago nunca se completa.

            return $nuevaVenta;
        });
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
