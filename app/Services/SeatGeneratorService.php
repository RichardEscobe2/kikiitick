<?php

namespace App\Services;

use App\Models\Teatro;
use App\Models\Asiento;
use App\Models\Evento;
use App\Models\AsientoEvento;
use Illuminate\Support\Facades\DB;

class SeatGeneratorService
{
    /**
     * Genera la matriz física global para TODO el recinto/teatro
     */
    public static function generarAsientosParaTeatro(Teatro $teatro): void
    {
        DB::transaction(function () use ($teatro) {
            // 1. Limpiar asientos previos del recinto si se reconfigura
            $teatro->asientos()->delete();

            $filasCount = $teatro->filas_totales ?? 10;
            $asientosPorFila = $teatro->asientos_por_fila ?? 20;
            $pasillos = is_array($teatro->pasillos_slots) 
                ? $teatro->pasillos_slots 
                : json_decode($teatro->pasillos_slots ?? '[]', true);

            $letrasFilas = range('A', 'Z');
            $asientosAInsertar = [];
            $now = now();

            // 2. Construir matriz por fila
            for ($f = 0; $f < $filasCount; $f++) {
                $nombreFila = $letrasFilas[$f] ?? ('F' . ($f + 1));
                $numeroAsiento = 1;

                $totalSlots = $asientosPorFila + count($pasillos);

                for ($s = 1; $s <= $totalSlots; $s++) {
                    $esPasillo = in_array($s, $pasillos);

                    $asientosAInsertar[] = [
                        'teatro_id'      => $teatro->id,
                        'zona_teatro_id' => null, // Se asignará cuando se configuren las zonas
                        'fila'           => $nombreFila,
                        'numero'         => $esPasillo ? -$s : $numeroAsiento, // Evita duplicados en pasillos
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

            // 3. Inserción masiva optimizada
            if (!empty($asientosAInsertar)) {
                Asiento::insert($asientosAInsertar);
            }
        });
    }

    /**
     * Sincroniza la disponibilidad de asientos cuando se crea un evento
     */
    public static function inicializarAsientosEvento(Evento $evento): void
    {
        $teatro = $evento->teatro()->with('asientos')->first();

        if (!$teatro) return;

        DB::transaction(function () use ($evento, $teatro) {
            foreach ($teatro->asientos as $asiento) {
                if ($asiento->tipo === 'asiento') {
                    AsientoEvento::firstOrCreate(
                        [
                            'evento_id'  => $evento->id,
                            'asiento_id' => $asiento->id,
                        ],
                        [
                            'estado'     => 'disponible',
                        ]
                    );
                }
            }
        });
    }
}