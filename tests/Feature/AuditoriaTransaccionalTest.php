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
 * Módulo 5 (Auditoría transaccional): CompraService/CompraController no
 * tenían ninguna cobertura de tests previa a esta tarea — dado que manejan
 * dinero e inventario real, se verifica el comportamiento REAL (se lee el
 * archivo de log de verdad después de cada acción) en vez de mockear
 * Log::channel(), que es frágil ante cualquier otra llamada de log
 * inesperada en el mismo request.
 */
class AuditoriaTransaccionalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ejecuta $accion() y devuelve SOLO el contenido que se agregó al log de
     * auditoría del día durante esa ejecución (offset antes/después) — así
     * cada test solo ve sus propias entradas, sin importar qué más haya en
     * el archivo real de entradas previas (manuales o de otros tests).
     */
    private function contenidoNuevoDeAuditoria(callable $accion): string
    {
        $archivo = storage_path('logs/auditoria-' . now()->format('Y-m-d') . '.log');
        $offsetInicial = file_exists($archivo) ? filesize($archivo) : 0;

        $accion();

        if (!file_exists($archivo)) {
            return '';
        }

        return substr(file_get_contents($archivo), $offsetInicial);
    }

    private function crearEventoConAsientos(int $numAsientos = 5): array
    {
        $organizador = User::factory()->organizador()->create();

        $teatro = Teatro::create([
            'usuario_id'         => $organizador->id,
            'nombre'             => 'Teatro Auditoria',
            'ubicacion'          => 'Calle Falsa 1',
            'capacidad_total'    => $numAsientos,
            'filas_totales'      => 1,
            'asientos_por_fila'  => $numAsientos,
            'pasillos_slots'     => [],
            'posicion_escenario' => 'arriba',
        ]);
        SeatGeneratorService::generarAsientosParaTeatro($teatro);

        $zona = ZonaTeatro::create([
            'teatro_id'          => $teatro->id,
            'nombre_zona'        => 'General',
            'nivel_proximidad'   => '1x',
            'capacidad_asientos' => $numAsientos,
            'fila_inicio'        => 'A',
            'fila_fin'           => 'A',
        ]);
        Asiento::where('teatro_id', $teatro->id)->update(['zona_teatro_id' => $zona->id]);

        $evento = Evento::create([
            'teatro_id'  => $teatro->id,
            'titulo'     => 'Evento Auditoria',
            'imagen_url' => 'https://example.com/x.jpg',
            'categoria'  => 'Concierto',
            'fecha_hora' => now()->addDays(5),
            'estatus'    => 'activo',
        ]);

        BoletoEvento::create([
            'evento_id'        => $evento->id,
            'zona_teatro_id'   => $zona->id,
            'precio_base'      => 300,
            'stock_disponible' => $numAsientos,
        ]);

        SeatGeneratorService::inicializarAsientosEvento($evento);

        $asientoIds = Asiento::where('teatro_id', $teatro->id)->orderBy('slot_index')->pluck('id')->all();

        return compact('organizador', 'teatro', 'zona', 'evento', 'asientoIds');
    }

    public function test_reservar_asientos_registra_auditoria(): void
    {
        ['evento' => $evento, 'asientoIds' => $asientoIds] = $this->crearEventoConAsientos();
        $cliente = User::factory()->create();

        $nuevo = $this->contenidoNuevoDeAuditoria(function () use ($cliente, $evento, $asientoIds) {
            $this->actingAs($cliente)->postJson('/api/boletos/reservar', [
                'evento_id'   => $evento->id,
                'asiento_ids' => [$asientoIds[0], $asientoIds[1]],
            ])->assertStatus(200);
        });

        $this->assertStringContainsString('AUDITORIA_RESERVA: Asientos bloqueados temporalmente', $nuevo);
        $this->assertStringContainsString('"usuario_id":' . $cliente->id, $nuevo);
        $this->assertStringContainsString('"evento_id":' . $evento->id, $nuevo);
        $this->assertStringContainsString((string) $asientoIds[0], $nuevo);
    }

    public function test_liberacion_de_reserva_expirada_registra_auditoria(): void
    {
        ['evento' => $evento, 'asientoIds' => $asientoIds] = $this->crearEventoConAsientos();
        $clienteOriginal = User::factory()->create();
        $clienteNuevo = User::factory()->create();

        // Reserva real (no expirada) del primer cliente.
        $this->actingAs($clienteOriginal)->postJson('/api/boletos/reservar', [
            'evento_id'   => $evento->id,
            'asiento_ids' => [$asientoIds[0]],
        ])->assertStatus(200);

        // La forzamos a estar vencida directamente en BD (no hace falta esperar
        // los 5 minutos reales de RN-05 para probar la limpieza).
        AsientoEvento::where('evento_id', $evento->id)
            ->where('asiento_id', $asientoIds[0])
            ->update(['reservado_hasta' => now()->subMinute()]);

        // Cualquier otra reserva sobre el mismo evento dispara la limpieza de
        // expiradas (reservarAsientos() la corre al inicio, antes de validar).
        $nuevo = $this->contenidoNuevoDeAuditoria(function () use ($clienteNuevo, $evento, $asientoIds) {
            $this->actingAs($clienteNuevo)->postJson('/api/boletos/reservar', [
                'evento_id'   => $evento->id,
                'asiento_ids' => [$asientoIds[1]],
            ])->assertStatus(200);
        });

        $this->assertStringContainsString('AUDITORIA_RESERVA_EXPIRADA: Liberación de asientos', $nuevo);
        $this->assertStringContainsString('"evento_id":' . $evento->id, $nuevo);
        $this->assertStringContainsString('"cantidad":1', $nuevo);

        // El asiento realmente quedó disponible para el segundo cliente.
        $this->assertDatabaseHas('asientos_evento', [
            'evento_id'  => $evento->id,
            'asiento_id' => $asientoIds[0],
            'estado'     => 'disponible',
        ]);
    }

    public function test_venta_pos_registra_auditoria(): void
    {
        ['evento' => $evento, 'asientoIds' => $asientoIds] = $this->crearEventoConAsientos();
        $vendedor = User::factory()->create(['rol' => 'vendedor']);

        $nuevo = $this->contenidoNuevoDeAuditoria(function () use ($vendedor, $evento, $asientoIds) {
            $this->actingAs($vendedor)->postJson('/api/boletos/comprar-pos', [
                'evento_id'     => $evento->id,
                'asiento_ids'   => [$asientoIds[0]],
                'metodo_pago'   => 'efectivo',
                'cliente_email' => 'comprador.mostrador@ejemplo.com',
            ])->assertStatus(201);
        });

        $this->assertStringContainsString('AUDITORIA_VENTA_POS: Venta en taquilla realizada', $nuevo);
        $this->assertStringContainsString('"vendedor_usuario_id":' . $vendedor->id, $nuevo);
        $this->assertStringContainsString('comprador.mostrador@ejemplo.com', $nuevo);
        $this->assertStringContainsString('"metodo_pago":"efectivo"', $nuevo);
    }

    public function test_venta_web_confirmada_registra_auditoria(): void
    {
        ['evento' => $evento, 'asientoIds' => $asientoIds] = $this->crearEventoConAsientos();
        $cliente = User::factory()->create();

        $this->actingAs($cliente)->postJson('/api/boletos/reservar', [
            'evento_id'   => $evento->id,
            'asiento_ids' => [$asientoIds[0]],
        ])->assertStatus(200);

        // 🛡️ Se crea la Venta 'pendiente' llamando DIRECTO a CompraService (no al
        // endpoint HTTP /api/boletos/comprar): ese endpoint, después de crear la
        // Venta, también llama a MercadoPagoService::crearPreferencia(), que hace
        // una petición HTTP real a la API de Mercado Pago — dependencia externa
        // que no debe determinar si un test unitario pasa o falla. CompraService::
        // procesarCompra() en sí NUNCA llama a Mercado Pago (eso lo hace el
        // controlador por separado), así que evita el problema por completo.
        $venta = app(\App\Services\CompraService::class)->procesarCompra(
            $cliente,
            $evento->id,
            [$asientoIds[0]],
            'tarjeta'
        );

        // simular-pago reutiliza confirmarPagoAprobado() — el mismo camino que
        // el webhook real de Mercado Pago (RN-11) — y solo está disponible
        // fuera de producción (APP_ENV=testing en phpunit.xml lo permite).
        $nuevo = $this->contenidoNuevoDeAuditoria(function () use ($cliente, $venta) {
            $this->actingAs($cliente)->postJson("/api/ventas/{$venta->id}/simular-pago")->assertStatus(200);
        });

        $ventaId = $venta->id;

        $this->assertStringContainsString('AUDITORIA_VENTA_WEB: Venta exitosa', $nuevo);
        $this->assertStringContainsString('"venta_id":' . $ventaId, $nuevo);
        $this->assertStringContainsString('"usuario_id":' . $cliente->id, $nuevo);
        $this->assertStringContainsString('"cantidad_asientos":1', $nuevo);
    }
}
