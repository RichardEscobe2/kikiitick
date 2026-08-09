<?php

namespace App\Console\Commands;

use App\Models\Asiento;
use App\Models\BoletoEvento;
use App\Models\Evento;
use App\Models\ZonaTeatro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repara la causa raíz de "seats con precio null/0" en GET /api/eventos/{id}/mapa:
 *
 * 1. Reconstruye el vínculo asientos.zona_teatro_id para recintos donde una
 *    regeneración de matriz previa (antes del fix en SeatGeneratorService) borró ese
 *    vínculo silenciosamente, usando el rango fila_inicio/fila_fin de cada ZonaTeatro
 *    (que sí sobrevivió intacto) para reconstruirlo.
 * 2. Asigna una tarifa base por defecto a zonas de eventos ACTIVOS que nunca fueron
 *    precificadas por el organizador (sin BoletoEvento). No sobrescribe tarifas ya
 *    configuradas — es un respaldo temporal, el organizador debe ajustarlas vía
 *    EventoController::guardarPrecios().
 *
 * Idempotente: puede ejecutarse repetidas veces sin efectos secundarios.
 */
class RepararIntegridadPrecios extends Command
{
    protected $signature = 'kikiitick:reparar-precios-mapa';

    protected $description = 'Repara vínculos asiento-zona huérfanos y asigna tarifa base a zonas de eventos activos sin precio configurado.';

    /**
     * Tarifa base por defecto según nivel de proximidad — solo como respaldo temporal.
     */
    private const TARIFA_DEFAULT_POR_NIVEL = [
        '0x' => 50.00,
        '1x' => 150.00,
        '2x' => 250.00,
        '3x' => 350.00,
        '4x' => 500.00,
    ];

    public function handle(): int
    {
        DB::transaction(function () {
            $this->repararVinculosZonaAsiento();
            $this->asignarTarifasFaltantes();
        }, attempts: 3);

        return self::SUCCESS;
    }

    private function repararVinculosZonaAsiento(): void
    {
        $zonas = ZonaTeatro::whereNotNull('fila_inicio')->whereNotNull('fila_fin')->get();
        $totalReparados = 0;

        foreach ($zonas as $zona) {
            $filasRango = range(strtoupper($zona->fila_inicio), strtoupper($zona->fila_fin));

            $afectados = Asiento::where('teatro_id', $zona->teatro_id)
                ->whereIn('fila', $filasRango)
                ->where(function ($q) use ($zona) {
                    $q->whereNull('zona_teatro_id')->orWhere('zona_teatro_id', '!=', $zona->id);
                })
                ->update(['zona_teatro_id' => $zona->id]);

            $totalReparados += $afectados;
        }

        $this->info("Vínculos asiento→zona reparados: {$totalReparados} asientos actualizados.");
    }

    private function asignarTarifasFaltantes(): void
    {
        $totalCreados = 0;

        foreach (Evento::where('estatus', 'activo')->get() as $evento) {
            $zonas = ZonaTeatro::where('teatro_id', $evento->teatro_id)->get();

            foreach ($zonas as $zona) {
                $yaExiste = BoletoEvento::where('evento_id', $evento->id)
                    ->where('zona_teatro_id', $zona->id)
                    ->exists();

                if ($yaExiste) {
                    continue;
                }

                BoletoEvento::create([
                    'evento_id'        => $evento->id,
                    'zona_teatro_id'   => $zona->id,
                    'precio_base'      => self::TARIFA_DEFAULT_POR_NIVEL[$zona->nivel_proximidad] ?? 250.00,
                    'stock_disponible' => $zona->capacidad_asientos,
                ]);

                $totalCreados++;
            }
        }

        $this->info("Tarifas por defecto asignadas: {$totalCreados} zonas de eventos activos que no tenían precio configurado.");
    }
}
