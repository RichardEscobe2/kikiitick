<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\AsientoEvento;
use App\Models\BoletoEvento;
use App\Models\Evento;
use App\Models\Teatro;
use App\Models\User;
use App\Models\ZonaTeatro;
use App\Services\SeatGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Módulo 2 (Geometría del recinto / pasillos) y Módulo 4 (Mapa interactivo):
 * cubre que las columnas de pasillo configuradas en Teatro::pasillos_slots se
 * generen como slots físicos NO vendibles, y que /api/eventos/{id}/mapa las
 * exponga correctamente marcadas para que el frontend las renderice como
 * corredor, no como asiento.
 */
class TeatroAsientosTest extends TestCase
{
    use RefreshDatabase;

    private function crearTeatroConPasillos(array $pasillos, int $filas = 1, int $asientosPorFila = 20): Teatro
    {
        $organizador = User::factory()->organizador()->create();

        $teatro = Teatro::create([
            'usuario_id'         => $organizador->id,
            'nombre'             => 'Teatro de Prueba',
            'ubicacion'          => 'Calle Falsa 123',
            'capacidad_total'    => $filas * $asientosPorFila,
            'filas_totales'      => $filas,
            'asientos_por_fila'  => $asientosPorFila,
            'pasillos_slots'     => $pasillos,
            'posicion_escenario' => 'arriba',
        ]);

        SeatGeneratorService::generarAsientosParaTeatro($teatro);

        return $teatro;
    }

    private function crearEventoActivo(Teatro $teatro): Evento
    {
        return Evento::create([
            'teatro_id'  => $teatro->id,
            'titulo'     => 'Evento de Prueba',
            'imagen_url' => 'https://example.com/img.jpg',
            'categoria'  => 'Concierto',
            'fecha_hora' => now()->addDays(5),
            'estatus'    => 'activo',
        ]);
    }

    public function test_genera_las_columnas_de_pasillo_exactas_configuradas(): void
    {
        $teatro = $this->crearTeatroConPasillos([6, 12], filas: 3, asientosPorFila: 20);

        // 20 asientos + 2 columnas de pasillo = 22 slots por fila x 3 filas.
        $this->assertDatabaseCount('asientos', 3 * 22);

        $this->assertDatabaseHas('asientos', ['teatro_id' => $teatro->id, 'fila' => 'A', 'slot_index' => 6, 'tipo' => 'pasillo']);
        $this->assertDatabaseHas('asientos', ['teatro_id' => $teatro->id, 'fila' => 'A', 'slot_index' => 12, 'tipo' => 'pasillo']);

        $this->assertSame(20, Asiento::where('teatro_id', $teatro->id)->where('fila', 'A')->where('tipo', 'asiento')->count());
        $this->assertSame(2, Asiento::where('teatro_id', $teatro->id)->where('fila', 'A')->where('tipo', 'pasillo')->count());
    }

    public function test_teatro_sin_pasillos_configurados_no_genera_ninguno(): void
    {
        $teatro = $this->crearTeatroConPasillos([], filas: 2, asientosPorFila: 10);

        $this->assertDatabaseCount('asientos', 2 * 10);
        $this->assertDatabaseMissing('asientos', ['teatro_id' => $teatro->id, 'tipo' => 'pasillo']);
    }

    public function test_numeracion_de_asientos_salta_las_columnas_de_pasillo(): void
    {
        $teatro = $this->crearTeatroConPasillos([6, 12], filas: 1, asientosPorFila: 20);

        $numeros = Asiento::where('teatro_id', $teatro->id)
            ->where('fila', 'A')
            ->where('tipo', 'asiento')
            ->orderBy('slot_index')
            ->pluck('numero')
            ->all();

        // Numeración física continua 1..20 para los asientos reales, sin saltos
        // por los pasillos (el pasillo consume un slot_index, no un número de
        // asiento — así el frontend puede numerar "A1, A2... A20" sin huecos).
        $this->assertSame(range(1, 20), $numeros);
    }

    public function test_pasillos_no_generan_inventario_vendible_para_el_evento(): void
    {
        $teatro = $this->crearTeatroConPasillos([6, 12], filas: 2, asientosPorFila: 20);
        $evento = $this->crearEventoActivo($teatro);

        SeatGeneratorService::inicializarAsientosEvento($evento);

        // 2 filas x 20 asientos reales = 40 filas en asientos_evento; las 4
        // posiciones de pasillo (2 filas x 2 columnas) nunca generan fila ahí.
        $this->assertDatabaseCount('asientos_evento', 40);

        $idsPasillo = Asiento::where('teatro_id', $teatro->id)->where('tipo', 'pasillo')->pluck('id');
        $this->assertSame(0, AsientoEvento::whereIn('asiento_id', $idsPasillo)->count());
    }

    public function test_mapa_del_evento_marca_es_pasillo_en_las_posiciones_correctas(): void
    {
        $teatro = $this->crearTeatroConPasillos([6, 12], filas: 1, asientosPorFila: 20);

        $zona = ZonaTeatro::create([
            'teatro_id'          => $teatro->id,
            'nombre_zona'        => 'General',
            'nivel_proximidad'   => '1x',
            'capacidad_asientos' => 20,
            'fila_inicio'        => 'A',
            'fila_fin'           => 'A',
        ]);
        Asiento::where('teatro_id', $teatro->id)->update(['zona_teatro_id' => $zona->id]);

        $evento = $this->crearEventoActivo($teatro);

        BoletoEvento::create([
            'evento_id'         => $evento->id,
            'zona_teatro_id'    => $zona->id,
            'precio_base'       => 350,
            'stock_disponible'  => 20,
        ]);

        SeatGeneratorService::inicializarAsientosEvento($evento);

        $response = $this->getJson("/api/eventos/{$evento->id}/mapa");
        $response->assertStatus(200);

        $asientos = collect($response->json('asientos'))->keyBy('slot_index');

        // 20 asientos + 2 columnas de pasillo ([6, 12]) = 22 posiciones físicas
        // totales, TODAS presentes en el mapa (el pasillo no se omite del
        // payload — el frontend lo necesita para dibujar el hueco/corredor en
        // el lugar exacto, no solo para saber cuántos asientos vendibles hay).
        $this->assertCount(22, $response->json('asientos'));

        $this->assertTrue($asientos[6]['es_pasillo']);
        $this->assertTrue($asientos[12]['es_pasillo']);

        $this->assertFalse($asientos[1]['es_pasillo']);
        $this->assertSame(1, $asientos[1]['numero']);

        // Último slot físico (22) es el asiento real número 20 (el 20º después
        // de descontar los 2 slots de pasillo intermedios).
        $this->assertFalse($asientos[22]['es_pasillo']);
        $this->assertSame(20, $asientos[22]['numero']);

        // Metadata explícita de geometría a nivel de teatro (Módulo 4): el
        // frontend la usa para decisiones de layout sin tener que recalcular
        // asientos_por_fila + count(pasillos_slots) por su cuenta.
        $response->assertJsonPath('teatro.pasillos', [6, 12]);
        $response->assertJsonPath('teatro.total_columnas', 22); // 20 asientos + 2 pasillos
    }

    public function test_mapa_de_teatro_sin_pasillos_expone_metadata_vacia(): void
    {
        $teatro = $this->crearTeatroConPasillos([], filas: 1, asientosPorFila: 15);

        $zona = ZonaTeatro::create([
            'teatro_id'          => $teatro->id,
            'nombre_zona'        => 'General',
            'nivel_proximidad'   => '1x',
            'capacidad_asientos' => 15,
            'fila_inicio'        => 'A',
            'fila_fin'           => 'A',
        ]);
        Asiento::where('teatro_id', $teatro->id)->update(['zona_teatro_id' => $zona->id]);

        $evento = $this->crearEventoActivo($teatro);

        BoletoEvento::create([
            'evento_id'        => $evento->id,
            'zona_teatro_id'   => $zona->id,
            'precio_base'      => 200,
            'stock_disponible' => 15,
        ]);

        SeatGeneratorService::inicializarAsientosEvento($evento);

        $response = $this->getJson("/api/eventos/{$evento->id}/mapa");
        $response->assertStatus(200);

        // Sin pasillos configurados: la fila se renderiza continua, sin huecos.
        $response->assertJsonPath('teatro.pasillos', []);
        $response->assertJsonPath('teatro.total_columnas', 15);
        $this->assertCount(15, $response->json('asientos'));
        $this->assertFalse(collect($response->json('asientos'))->contains('es_pasillo', true));
    }

    /**
     * 🐛 Regresión real encontrada en vivo (Playwright + captura de pantalla):
     * MySQL no garantiza el orden de fila sin ORDER BY explícito. Sin el
     * orderBy('fila')->orderBy('slot_index') en Teatro::asientos(), la API
     * devolvía los slots de pasillo agrupados al PRINCIPIO de cada fila en vez
     * de intercalados en su posición real — el frontend, que simplemente
     * recorre el arreglo tal como llega (sin reordenar), terminaba dibujando
     * ambos huecos pegados al borde izquierdo, casi imperceptibles, en vez de
     * separar bloques de butacas como "1-4 | hueco | 5-8 | hueco | 9-20".
     *
     * Los demás tests de este archivo NO habrían detectado esto: siempre
     * ordenan explícitamente en la propia aserción (->orderBy('slot_index')),
     * lo cual enmascara justo el bug que vivía en la relación Eloquent usada
     * por el controlador. Este test consume la respuesta de la API SIN
     * reordenarla, replicando exactamente lo que hace el frontend.
     */
    public function test_el_mapa_devuelve_los_asientos_de_cada_fila_en_orden_de_slot_index(): void
    {
        $teatro = $this->crearTeatroConPasillos([5, 10], filas: 1, asientosPorFila: 20);

        $zona = ZonaTeatro::create([
            'teatro_id'          => $teatro->id,
            'nombre_zona'        => 'General',
            'nivel_proximidad'   => '1x',
            'capacidad_asientos' => 20,
            'fila_inicio'        => 'A',
            'fila_fin'           => 'A',
        ]);
        Asiento::where('teatro_id', $teatro->id)->update(['zona_teatro_id' => $zona->id]);

        $evento = $this->crearEventoActivo($teatro);

        BoletoEvento::create([
            'evento_id'        => $evento->id,
            'zona_teatro_id'   => $zona->id,
            'precio_base'      => 300,
            'stock_disponible' => 20,
        ]);

        SeatGeneratorService::inicializarAsientosEvento($evento);

        $response = $this->getJson("/api/eventos/{$evento->id}/mapa");
        $response->assertStatus(200);

        // Sin reordenar en el test: tal como el frontend lo consume tal cual.
        $slotIndicesRecibidos = collect($response->json('asientos'))
            ->where('fila', 'A')
            ->pluck('slot_index')
            ->all();

        $this->assertSame(range(1, 22), $slotIndicesRecibidos);
    }
}
