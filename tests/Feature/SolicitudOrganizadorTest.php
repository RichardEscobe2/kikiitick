<?php

namespace Tests\Feature;

use App\Models\Teatro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolicitudOrganizadorTest extends TestCase
{
    use RefreshDatabase;

    private function payloadValido(array $overrides = []): array
    {
        return array_merge([
            'recinto_nombre'    => 'Teatro Gran Recinto',
            'recinto_direccion' => 'Av. Benito Juárez 123, Centro',
            'recinto_capacidad' => 500,
            'telefono_contacto' => '5512345678', // exactamente 10 dígitos
            'rfc'               => 'XAXX010101000',
            'descripcion'       => 'Conciertos y obras de teatro.',
        ], $overrides);
    }

    public function test_invitado_no_puede_solicitar(): void
    {
        $response = $this->postJson('/api/solicitud-organizador', $this->payloadValido());

        $response->assertStatus(401);
    }

    public function test_cliente_puede_solicitar_convertirse_en_organizador(): void
    {
        $cliente = User::factory()->create(['estatus_organizador' => 'ninguno']);

        $response = $this->actingAs($cliente)->postJson('/api/solicitud-organizador', $this->payloadValido());

        $response->assertStatus(200)
            ->assertJsonPath('usuario.estatus_organizador', 'pendiente')
            ->assertJsonPath('usuario.solicitud_organizador.recinto_nombre', 'Teatro Gran Recinto');

        $this->assertDatabaseHas('usuarios', [
            'id'                  => $cliente->id,
            'estatus_organizador' => 'pendiente',
            'rol'                 => 'cliente',
        ]);

        $this->assertDatabaseHas('solicitudes_organizador', [
            'usuario_id'         => $cliente->id,
            'recinto_nombre'     => 'Teatro Gran Recinto',
            'recinto_direccion'  => 'Av. Benito Juárez 123, Centro',
            'recinto_capacidad'  => 500,
            'telefono_contacto'  => '5512345678',
            'rfc'                => 'XAXX010101000',
        ]);
    }

    public function test_campos_obligatorios_son_requeridos(): void
    {
        $cliente = User::factory()->create();

        $response = $this->actingAs($cliente)->postJson('/api/solicitud-organizador', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recinto_nombre', 'recinto_direccion', 'recinto_capacidad', 'telefono_contacto']);

        // rfc y descripcion son opcionales: no deben aparecer como error.
        $response->assertJsonMissingValidationErrors(['rfc', 'descripcion']);
    }

    public function test_rechaza_telefono_con_caracteres_invalidos(): void
    {
        $cliente = User::factory()->create();

        $response = $this->actingAs($cliente)->postJson(
            '/api/solicitud-organizador',
            $this->payloadValido(['telefono_contacto' => 'llamame@x.com'])
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['telefono_contacto']);
    }

    public function test_rechaza_capacidad_no_positiva(): void
    {
        $cliente = User::factory()->create();

        $response = $this->actingAs($cliente)->postJson(
            '/api/solicitud-organizador',
            $this->payloadValido(['recinto_capacidad' => 0])
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['recinto_capacidad']);
    }

    public function test_rechaza_telefono_que_no_tenga_exactamente_10_digitos(): void
    {
        $cliente = User::factory()->create();

        // Corto (9), largo (11), y con formato/símbolos que digits:10 también
        // rechaza aunque tenga 10 dígitos "visibles" (espacios/+ no cuentan
        // como dígitos para esta regla — coincide con que el frontend ya ni
        // deja escribir esos caracteres en el campo).
        foreach (['551234567', '55123456789', '+52 551234567'] as $telefonoInvalido) {
            $response = $this->actingAs($cliente)->postJson(
                '/api/solicitud-organizador',
                $this->payloadValido(['telefono_contacto' => $telefonoInvalido])
            );

            $response->assertStatus(422)->assertJsonValidationErrors(['telefono_contacto']);
        }
    }

    public function test_rechaza_rfc_con_formato_invalido(): void
    {
        $cliente = User::factory()->create();

        // Muy corto (11) y en minúsculas — ninguno cumple ^[A-Z0-9]{12,13}$
        $response = $this->actingAs($cliente)->postJson(
            '/api/solicitud-organizador',
            $this->payloadValido(['rfc' => 'abc12345678'])
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['rfc']);
    }

    public function test_solicitud_sin_rfc_es_valida_por_ser_opcional(): void
    {
        $cliente = User::factory()->create();

        // El frontend envía '' por defecto cuando el campo opcional queda vacío;
        // el controlador debe tratarlo como ausente, no como formato inválido.
        $response = $this->actingAs($cliente)->postJson(
            '/api/solicitud-organizador',
            $this->payloadValido(['rfc' => '', 'descripcion' => ''])
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('solicitudes_organizador', [
            'usuario_id' => $cliente->id,
            'rfc'        => null,
        ]);
    }

    public function test_rechaza_nombre_de_recinto_con_simbolos(): void
    {
        $cliente = User::factory()->create();

        $response = $this->actingAs($cliente)->postJson(
            '/api/solicitud-organizador',
            $this->payloadValido(['recinto_nombre' => 'Arena #1 @Central!'])
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['recinto_nombre']);
    }

    public function test_rechaza_nombre_de_recinto_con_numeros(): void
    {
        // Regla endurecida en esta iteración: antes "Sala 1" era válido, ahora
        // el nombre del recinto no admite NINGÚN dígito, solo letras y espacios.
        $cliente = User::factory()->create();

        $response = $this->actingAs($cliente)->postJson(
            '/api/solicitud-organizador',
            $this->payloadValido(['recinto_nombre' => 'Sala 1'])
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['recinto_nombre']);
    }

    public function test_acepta_nombre_de_recinto_con_acentos_y_enie(): void
    {
        $cliente = User::factory()->create();

        $response = $this->actingAs($cliente)->postJson(
            '/api/solicitud-organizador',
            $this->payloadValido(['recinto_nombre' => 'Salón Fiésta Ñoño'])
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('solicitudes_organizador', [
            'usuario_id'     => $cliente->id,
            'recinto_nombre' => 'Salón Fiésta Ñoño',
        ]);
    }

    public function test_cliente_con_solicitud_ya_rechazada_puede_reintentar(): void
    {
        $cliente = User::factory()->estatusOrganizador('rechazado')->create();

        $response = $this->actingAs($cliente)->postJson('/api/solicitud-organizador', $this->payloadValido());

        $response->assertStatus(200);
        $this->assertDatabaseHas('usuarios', [
            'id'                  => $cliente->id,
            'estatus_organizador' => 'pendiente',
        ]);
    }

    public function test_reenviar_solicitud_sobreescribe_la_anterior_sin_duplicar(): void
    {
        $cliente = User::factory()->estatusOrganizador('rechazado')->create();

        $this->actingAs($cliente)->postJson('/api/solicitud-organizador', $this->payloadValido(['recinto_nombre' => 'Nombre Viejo']));
        $cliente->update(['estatus_organizador' => 'rechazado']); // simula un segundo rechazo
        $this->actingAs($cliente)->postJson('/api/solicitud-organizador', $this->payloadValido(['recinto_nombre' => 'Nombre Nuevo']));

        $this->assertDatabaseCount('solicitudes_organizador', 1);
        $this->assertDatabaseHas('solicitudes_organizador', [
            'usuario_id'     => $cliente->id,
            'recinto_nombre' => 'Nombre Nuevo',
        ]);
    }

    public function test_no_permite_duplicar_una_solicitud_pendiente(): void
    {
        $cliente = User::factory()->estatusOrganizador('pendiente')->create();

        $response = $this->actingAs($cliente)->postJson('/api/solicitud-organizador', $this->payloadValido());

        $response->assertStatus(409);
        $this->assertDatabaseHas('usuarios', [
            'id'                  => $cliente->id,
            'estatus_organizador' => 'pendiente',
        ]);
    }

    public function test_un_organizador_ya_aprobado_no_puede_volver_a_solicitar(): void
    {
        // rol y estatus_organizador siempre cambian juntos en aprobarOrganizador()
        // (nunca queda estatus_organizador='aprobado' con rol='cliente'), así que
        // el guard de rol (403) del controlador se dispara antes que el de
        // estatus (409) — el 409 "ya es organizador" es defensivo, para el caso
        // hipotético de una fila inconsistente, no el camino normal.
        $organizador = User::factory()->organizador()->create();

        $response = $this->actingAs($organizador)->postJson('/api/solicitud-organizador', $this->payloadValido());

        $response->assertStatus(403);
    }

    public function test_un_rol_distinto_de_cliente_no_puede_solicitar(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/solicitud-organizador', $this->payloadValido());

        $response->assertStatus(403);
    }

    public function test_flujo_completo_solicitud_y_aprobacion_por_admin(): void
    {
        $cliente = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($cliente)
            ->postJson('/api/solicitud-organizador', $this->payloadValido())
            ->assertStatus(200);

        // La solicitud debe aparecer en la cola de revisión del admin, con los
        // datos reales de recinto/contacto (no placeholders).
        $this->actingAs($admin)
            ->getJson('/api/admin/solicitudes-organizador')
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $cliente->id])
            ->assertJsonFragment(['recinto_nombre' => 'Teatro Gran Recinto']);

        $this->actingAs($admin)
            ->putJson("/api/admin/organizador/{$cliente->id}/aprobar")
            ->assertStatus(200);

        $this->assertDatabaseHas('usuarios', [
            'id'                  => $cliente->id,
            'rol'                 => 'organizador',
            'estatus_organizador' => 'aprobado',
        ]);
    }

    public function test_aprobar_solicitud_del_autoservicio_crea_el_teatro_automaticamente(): void
    {
        $cliente = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($cliente)->postJson('/api/solicitud-organizador', $this->payloadValido([
            'recinto_nombre'    => 'Arena Central',
            'recinto_direccion' => 'Calle Falsa 123',
            'recinto_capacidad' => 800,
        ]))->assertStatus(200);

        $this->assertDatabaseMissing('teatros', ['usuario_id' => $cliente->id]);

        $this->actingAs($admin)
            ->putJson("/api/admin/organizador/{$cliente->id}/aprobar")
            ->assertStatus(200);

        $this->assertDatabaseHas('teatros', [
            'usuario_id'      => $cliente->id,
            'nombre'          => 'Arena Central',
            'ubicacion'       => 'Calle Falsa 123',
            'capacidad_total' => 800,
        ]);

        $teatro = Teatro::where('usuario_id', $cliente->id)->firstOrFail();
        $this->assertDatabaseHas('asientos', ['teatro_id' => $teatro->id]);
    }

    public function test_aprobar_no_duplica_teatro_si_ya_existe(): void
    {
        // Camino de invitado (registerOrganizador): ya trae su propio Teatro
        // creado desde el registro, sin pasar por solicitudes_organizador.
        $organizadorPendiente = User::factory()->estatusOrganizador('pendiente')->create();
        Teatro::create([
            'usuario_id'      => $organizadorPendiente->id,
            'nombre'          => 'Teatro Ya Existente',
            'ubicacion'       => 'Ya Registrado 456',
            'capacidad_total' => 300,
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson("/api/admin/organizador/{$organizadorPendiente->id}/aprobar")
            ->assertStatus(200);

        $this->assertDatabaseCount('teatros', 1);
    }
}
