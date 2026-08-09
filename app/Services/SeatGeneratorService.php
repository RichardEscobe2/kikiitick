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
            // 🛡️ RN-01: bloquear la regeneración/destrucción de la matriz si el recinto tiene
            // asientos 'reservado' o 'vendido' en algún evento asociado. Autoprotegido aquí
            // dentro del servicio (no solo en el controlador que lo invoca) para que CUALQUIER
            // caller (job, tinker, futuro endpoint) quede cubierto, y con lockForUpdate() para
            // cerrar la condición de carrera TOCTOU entre el check y el delete() siguiente.
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

            // 4. 🛡️ Restaurar el vínculo asiento → zona para las zonas ya configuradas en
            // este recinto. El paso 1 borra y recrea TODOS los Asiento (siempre con
            // zona_teatro_id = null); sin este paso, cada regeneración posterior a la
            // configuración de zonas desvincularía silenciosamente cada butaca de su
            // ZonaTeatro — y por lo tanto de su tarifa en BoletoEvento — causando que el
            // mapa del evento devuelva precio 0/null pese a que la tarifa sí existe.
            // fila_inicio/fila_fin sobreviven intactos en zonas_teatro, así que se usan
            // para reconstruir el vínculo (mismo criterio que TeatroController::storeZona()).
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

    /**
     * Sincroniza la disponibilidad de asientos cuando se crea un evento
     */
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