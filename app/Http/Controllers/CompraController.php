<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Asiento;
use App\Models\AsientoEvento;
use App\Models\BoletoEvento;
use App\Models\Venta;
use App\Models\DetalleVenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

                // 1. Validar que la reserva activa le pertenece a este usuario
                $asientosValidos = AsientoEvento::where('evento_id', $eventoId)
                    ->whereIn('asiento_id', $asientoIds)
                    ->where('reservado_por_usuario_id', $usuario->id)
                    ->where('estado', 'reservado')
                    ->where('reservado_hasta', '>=', Carbon::now())
                    ->get();

                if ($asientosValidos->count() !== count($asientoIds)) {
                    throw new \Exception('Tu reserva de asientos ha expirado o no es válida. Vuelve a intentarlo.');
                }

                // 2. Agrupar asientos por Zona para calcular costo y detalles de venta
                $asientosFisicos = Asiento::whereIn('id', $asientoIds)->get();
                $asientosPorZona = $asientosFisicos->groupBy('zona_teatro_id');

                $montoNetoTotal = 0;
                $detallesParaCrear = [];

                foreach ($asientosPorZona as $zonaId => $asientosGrupo) {
                    $cantidad = $asientosGrupo->count();
                    $boletoEvento = BoletoEvento::where('evento_id', $eventoId)
                        ->where('zona_teatro_id', $zonaId)
                        ->firstOrFail();

                    $subtotalZona = $boletoEvento->precio_base * $cantidad;
                    $montoNetoTotal += $subtotalZona;

                    // Reducir stock disponible en la zona
                    $boletoEvento->decrement('stock_disponible', $cantidad);

                    $detallesParaCrear[] = [
                        'boleto_evento_id' => $boletoEvento->id,
                        'cantidad'         => $cantidad,
                        'subtotal'         => $subtotalZona,
                    ];
                }

                // 3. Calcular Comisiones KikiiTick
                $comisionPorBoleto = (float) $evento->comision_fija_empresa;
                $totalComisiones = count($asientoIds) * $comisionPorBoleto;
                $montoTotal = $montoNetoTotal + $totalComisiones;

                // 4. Crear registro en la tabla `ventas`
                $nuevaVenta = Venta::create([
                    'usuario_id'       => $usuario->id,
                    'monto_neto'       => $montoNetoTotal,
                    'total_comisiones' => $totalComisiones,
                    'monto_total'      => $montoTotal,
                    'estatus_pago'     => 'pagado',
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

                // 6. Cambiar estado de los asientos a 'vendido'
                AsientoEvento::where('evento_id', $eventoId)
                    ->whereIn('asiento_id', $asientoIds)
                    ->update([
                        'estado'                   => 'vendido',
                        'reservado_por_usuario_id' => null,
                        'reservado_hasta'          => null,
                    ]);

                return $nuevaVenta;
            });

            return response()->json([
                'message'  => '¡Compra procesada con éxito!',
                'venta_id' => $venta->id,
                'total'    => (float) $venta->monto_total,
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}