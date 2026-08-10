<?php

namespace App\Services;

use App\Exceptions\InventarioComprometidoException;
use App\Models\Teatro;
use App\Models\Asiento;
use App\Models\Evento;
use App\Models\AsientoEvento;
use App\Models\ZonaTeatro;
use Illuminate\Support\Facades\DB;

class SeatGeneratorService
{
    /**
     * Genera la matriz física global para TODO el recinto/teatro
     *
     * @throws InventarioComprometidoException
     */
    public static function generarAsientosParaTeatro(Teatro $teatro): void
    {
        DB::transaction(function () use ($teatro) {
            $tieneInventarioComprometido = DB::table('asientos_evento')
                ->join('asientos', 'asientos_evento.asiento_id', '=', 'asientos.id')
                ->where('asientos.teatro_id', $teatro->id)
                ->whereIn('asientos_evento.estado', ['reservado', 'vendido'])
                ->lockForUpdate()
                ->exists();

            if ($tieneInventarioComprometido) {
                throw new InventarioComprometidoException(
                    'No se puede modificar la distribución física del recinto: existen asientos reservados o vendidos en eventos asociados. Esta acción destruiría el historial de ventas.'
                );
            }
            $teatro->asientos()->delete();

            $filasCount = $teatro->filas_totales ?? 10;
            $asientosPorFila = $teatro->asientos_por_fila ?? 20;
            $pasillos = is_array($teatro->pasillos_slots) 
                ? $teatro->pasillos_slots 
                : json_decode($teatro->pasillos_slots ?? '[]', true);

            $letrasFilas = range('A', 'Z');
            $asientosAInsertar = [];
            $now = now();

            for ($f = 0; $f < $filasCount; $f++) {
                $nombreFila = $letrasFilas[$f] ?? ('F' . ($f + 1));
                $numeroAsiento = 1;

                $totalSlots = $asientosPorFila + count($pasillos);

                for ($s = 1; $s <= $totalSlots; $s++) {
                    $esPasillo = in_array($s, $pasillos);

                    $asientosAInsertar[] = [
                        'teatro_id'      => $teatro->id,
                        'zona_teatro_id' => null, 
                        'fila'           => $nombreFila,
                        'numero'         => $esPasillo ? -$s : $numeroAsiento, 
                        'codigo'         => $esPasillo ? 'PASILLO' : ($nombreFila . $numeroAsiento),
                        'slot_index'     => $s,
                        'tipo'           => $esPasillo ? 'pasillo' : 'asiento',
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];

                    if (!$esPasillo) {
                        $numeroAsiento++;
                    }
                }
            }

            if (!empty($asientosAInsertar)) {
                Asiento::insert($asientosAInsertar);
            }


            $zonas = ZonaTeatro::where('teatro_id', $teatro->id)
                ->whereNotNull('fila_inicio')
                ->whereNotNull('fila_fin')
                ->get(['id', 'fila_inicio', 'fila_fin']);

            foreach ($zonas as $zona) {
                $filasRango = range(strtoupper($zona->fila_inicio), strtoupper($zona->fila_fin));

                Asiento::where('teatro_id', $teatro->id)
                    ->whereIn('fila', $filasRango)
                    ->update(['zona_teatro_id' => $zona->id]);
            }
        }, attempts: 3);
    }




    
    public static function inicializarAsientosEvento(Evento $evento): void
    {
        $teatro = $evento->teatro()->with('asientos')->first();

        if (!$teatro) return;

        $now = now();

        $filasParaInsertar = $teatro->asientos
            ->where('tipo', 'asiento')
            ->map(fn ($asiento) => [
                'evento_id'  => $evento->id,
                'asiento_id' => $asiento->id,
                'estado'     => 'disponible',
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if (empty($filasParaInsertar)) return;

        AsientoEvento::upsert(
            $filasParaInsertar,
            ['evento_id', 'asiento_id'],
            ['estado']
        );
    }
}