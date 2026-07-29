<?php

namespace App\Http\Controllers;

use App\Exceptions\CompraException;
use App\Services\CompraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CompraController extends Controller
{
    public function __construct(private CompraService $compraService)
    {
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
     * Procesar la orden de compra: crea la Venta en estado 'pendiente' y emite los accesos.
     */
    public function procesarCompra(Request $request)
    {
        $validated = $request->validate([
            'evento_id'      => 'required|exists:eventos,id',
            'asiento_ids'    => 'required|array|min:1',
            'asiento_ids.*'  => 'exists:asientos,id',
        ]);

        try {
            $venta = $this->compraService->procesarCompra(
                $request->user(),
                $validated['evento_id'],
                $validated['asiento_ids']
            );

            return response()->json([
                'message'      => 'Orden creada, pendiente de confirmación de pago.',
                'venta_id'     => $venta->id,
                'estatus_pago' => $venta->estatus_pago,
                'total'        => (float) $venta->monto_total,
            ], 201);
        } catch (CompraException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Error inesperado al procesar la compra: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al procesar tu compra. Intenta nuevamente.'
            ], 500);
        }
    }
}
