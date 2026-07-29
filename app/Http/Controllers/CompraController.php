<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Asiento;
use App\Models\AsientoEvento;
use App\Models\BoletoEvento;
use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Acceso;
use App\Models\ZonaTeatro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CompraController extends Controller
{
    /**
     * POST /api/boletos/reservar
     * Bloquear asientos de forma temporal por 10 minutos
     */
    public function reservarAsientos(Request $request)
    {
        $usuario = $request->user();

        $validated = $request->validate([
            'evento_id'   => 'required|exists:eventos,id',
            'asiento_ids' => 'required|array|min:1',
            'asiento_ids.*' => 'exists:asientos,id'
        ]);

        $eventoId = $validated['evento_id'];
        $asientoIds = $validated['asiento_ids'];

        // 🛡️ RN-13: rechazar reservas sobre eventos que no están activos
        $evento = Evento::find($eventoId);

        if (!$evento || $evento->estatus !== 'activo') {
            return response()->json([
                'message' => 'Este evento no está disponible para reservas en este momento.'
            ], 409);
        }

        try {
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
                    throw new \Exception('Uno o más asientos seleccionados ya fueron apartados o vendidos. Por favor selecciona otros.');
                }

                // 3. Bloquear asientos por 10 minutos
                $expiraEn = Carbon::now()->addMinutes(10);

                foreach ($asientoIds as $asientoId) {
                    AsientoEvento::updateOrCreate(
                        [
                            'evento_id' => $eventoId,
                            'asiento_id' => $asientoId
                        ],
                        [
                            'estado'                   => 'reservado',
                            'reservado_por_usuario_id' => $usuario->id,
                            'reservado_hasta'          => $expiraEn,
                        ]
                    );
                }
            });

            return response()->json([
                'message'          => 'Asientos reservados temporalmente por 10 minutos.',
                'expira_en'        => Carbon::now()->addMinutes(10)->toIso8601String(),
                'tiempo_limite_seg' => 600
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/boletos/comprar
     * Procesar pago, generar orden de Venta y marcar asientos como 'vendido'
     */
    public function procesarCompra(Request $request)
    {
        $usuario = $request->user();

        $validated = $request->validate([
            'evento_id'   => 'required|exists:eventos,id',
            'asiento_ids' => 'required|array|min:1',
            'asiento_ids.*' => 'exists:asientos,id'
        ]);

        $eventoId = $validated['evento_id'];
        $asientoIds = $validated['asiento_ids'];

        try {
            $venta = DB::transaction(function () use ($usuario, $eventoId, $asientoIds) {
                $evento = Evento::findOrFail($eventoId);

                // 🛡️ RN-13: rechazar compras sobre eventos que no están activos
                if ($evento->estatus !== 'activo') {
                    throw new \Exception('Este evento no está disponible para procesar compras en este momento.');
                }

                // 1. Validar que la reserva activa le pertenece a este usuario
                //    🛡️ RN-08: lockForUpdate() evita que dos requests de checkout concurrentes
                //    procesen la misma reserva dos veces.
                $asientosValidos = AsientoEvento::where('evento_id', $eventoId)
                    ->whereIn('asiento_id', $asientoIds)
                    ->where('reservado_por_usuario_id', $usuario->id)
                    ->where('estado', 'reservado')
                    ->where('reservado_hasta', '>=', Carbon::now())
                    ->lockForUpdate()
                    ->get();

                if ($asientosValidos->count() !== count($asientoIds)) {
                    throw new \Exception('Tu reserva de asientos ha expirado o no es válida. Vuelve a intentarlo.');
                }

                // 2. Agrupar asientos por Zona para calcular costo y detalles de venta
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
                        throw new \Exception('Uno o más asientos seleccionados pertenecen a una zona sin tarifa configurada. Contacta al organizador o intenta con otros asientos.');
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

                // 3. Calcular Comisiones KikiiTick
                $comisionPorBoleto = (float) $evento->comision_fija_empresa;
                $totalComisiones = count($asientoIds) * $comisionPorBoleto;
                $montoTotal = $montoNetoTotal + $totalComisiones;

                // 4. Crear registro en la tabla `ventas`
                //    🛡️ RN-11: estado inicial 'pendiente' — el Módulo 5 lo confirma a 'pagado'
                //    únicamente tras la respuesta real de la pasarela de pago.
                $nuevaVenta = Venta::create([
                    'usuario_id'       => $usuario->id,
                    'monto_neto'       => $montoNetoTotal,
                    'total_comisiones' => $totalComisiones,
                    'monto_total'      => $montoTotal,
                    'estatus_pago'     => 'pendiente',
                    'fecha_venta'      => Carbon::now(),
                ]);

                // 5. Crear desglose en `detalles_venta`
                foreach ($detallesParaCrear as $detalle) {
                    DetalleVenta::create([
                        'venta_id'         => $nuevaVenta->id,
                        'boleto_evento_id' => $detalle['boleto_evento_id'],
                        'cantidad'         => $detalle['cantidad'],
                        'subtotal'         => $detalle['subtotal'],
                    ]);
                }

                // 6. 🛡️ RN-05: emitir un acceso/boleto individual (hash + QR) por cada asiento,
                //    en estado 'pendiente' hasta que el Módulo 5 confirme el pago.
                foreach ($asientosPorZona as $zonaId => $asientosGrupo) {
                    $boletoEvento = $boletosPorZona[$zonaId];
                    $zona = ZonaTeatro::find($zonaId);

                    foreach ($asientosGrupo as $asientoFisico) {
                        Acceso::create([
                            'venta_id'         => $nuevaVenta->id,
                            'boleto_evento_id' => $boletoEvento->id,
                            'clave_evento'     => (string) $eventoId,
                            'numero_control'   => strtoupper(Str::random(10)),
                            'hash_seguridad'   => hash('sha256', $nuevaVenta->id . '-' . $asientoFisico->id . '-' . config('app.key')),
                            'token_qr'         => (string) Str::uuid(),
                            'seccion_pasillo'  => $zona ? $zona->nombre_zona : 'General',
                            'fila_palco'       => $asientoFisico->fila,
                            'numero_asiento'   => (string) $asientoFisico->numero,
                            'estatus'          => 'pendiente',
                        ]);
                    }
                }

                // 🛡️ RN-11/RN-16: los asientos permanecen en 'reservado' — NO se marcan 'vendido'
                // aquí. Esa transición corresponde al Módulo 5 cuando la pasarela de pago
                // confirme el cobro real. El temporizador de reserva (reservado_hasta) sigue
                // vigente como red de seguridad si el pago nunca se completa.

                return $nuevaVenta;
            });

            return response()->json([
                'message'      => 'Orden creada, pendiente de confirmación de pago.',
                'venta_id'     => $venta->id,
                'estatus_pago' => $venta->estatus_pago,
                'total'        => (float) $venta->monto_total,
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}