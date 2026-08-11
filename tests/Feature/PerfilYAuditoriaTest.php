<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerfilYAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private array $archivosLogTemporales = [];

    protected function tearDown(): void
    {
        foreach ($this->archivosLogTemporales as $archivo) {
            @unlink($archivo);
        }
        parent::tearDown();
    }

    // ==========================================================
    // PATCH /api/user/perfil
    // ==========================================================

    public function test_invitado_no_puede_actualizar_perfil(): void
    {
        $response = $this->patchJson('/api/user/perfil', ['nombre' => 'Alguien']);

        $response->assertStatus(401);
    }

    public function test_usuario_puede_actualizar_su_perfil(): void
    {
        $usuario = User::factory()->create(['nombre' => 'Nombre Viejo', 'telefono' => null, 'rfc' => null]);

        $response = $this->actingAs($usuario)->patchJson('/api/user/perfil', [
            'nombre'   => 'Nombre Nuevo',
            'telefono' => '5512345678',
            'rfc'      => 'XAXX010101000',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.nombre', 'Nombre Nuevo')
            ->assertJsonPath('user.telefono', '5512345678')
            ->assertJsonPath('user.rfc', 'XAXX010101000');

        $this->assertDatabaseHas('usuarios', [
            'id'       => $usuario->id,
            'nombre'   => 'Nombre Nuevo',
            'telefono' => '5512345678',
        ]);
    }

    public function test_perfil_permite_telefono_y_rfc_vacios_por_ser_opcionales(): void
    {
        $usuario = User::factory()->create();

        $response = $this->actingAs($usuario)->patchJson('/api/user/perfil', [
            'nombre'   => 'Solo Nombre',
            'telefono' => '',
            'rfc'      => '',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('usuarios', ['id' => $usuario->id, 'telefono' => null, 'rfc' => null]);
    }

    public function test_rechaza_telefono_invalido_en_perfil(): void
    {
        $usuario = User::factory()->create();

        $response = $this->actingAs($usuario)->patchJson('/api/user/perfil', [
            'nombre'   => 'Alguien',
            'telefono' => '12345', // no son 10 dígitos
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['telefono']);
    }

    public function test_rechaza_rfc_invalido_en_perfil(): void
    {
        $usuario = User::factory()->create();

        $response = $this->actingAs($usuario)->patchJson('/api/user/perfil', [
            'nombre' => 'Alguien',
            'rfc'    => 'minusculas12', // no cumple ^[A-Z0-9]{12,13}$
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['rfc']);
    }

    public function test_rechaza_nombre_vacio_en_perfil(): void
    {
        $usuario = User::factory()->create();

        $response = $this->actingAs($usuario)->patchJson('/api/user/perfil', ['nombre' => '']);

        $response->assertStatus(422)->assertJsonValidationErrors(['nombre']);
    }

    public function test_perfil_no_permite_cambiar_correo_ni_rol_via_mass_assignment(): void
    {
        $usuario = User::factory()->create(['rol' => 'cliente']);
        $correoOriginal = $usuario->correo;

        $response = $this->actingAs($usuario)->patchJson('/api/user/perfil', [
            'nombre' => 'Alguien',
            'correo' => 'hackeado@ejemplo.com',
            'rol'    => 'admin',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('usuarios', [
            'id'     => $usuario->id,
            'correo' => $correoOriginal, // sin cambios
            'rol'    => 'cliente',       // sin cambios
        ]);
    }

    // ==========================================================
    // GET /api/admin/logs/auditoria
    // ==========================================================

    public function test_invitado_no_puede_ver_logs_de_auditoria(): void
    {
        $response = $this->getJson('/api/admin/logs/auditoria');

        $response->assertStatus(401);
    }

    public function test_un_no_admin_no_puede_ver_logs_de_auditoria(): void
    {
        $cliente = User::factory()->create();

        $response = $this->actingAs($cliente)->getJson('/api/admin/logs/auditoria');

        $response->assertStatus(403);
    }

    public function test_admin_puede_ver_y_parsear_los_logs_de_auditoria(): void
    {
        $admin = User::factory()->admin()->create();

        // Escribe un archivo de log real con el mismo formato que produce
        // Log::channel('auditoria') (Monolog LineFormatter estándar), usando
        // un correo único para no mezclarse con logs reales que pueda haber
        // en storage/logs/ de otras pruebas manuales de esta sesión.
        $correoUnico = 'auditoria.test.unico@kikiitick.local';
        $archivo = storage_path('logs/auditoria-test-' . uniqid() . '.log');
        $this->archivosLogTemporales[] = $archivo;

        file_put_contents($archivo, implode("\n", [
            '[2026-08-11 10:00:00] local.WARNING: AUDITORIA_AUTH_LOGIN: Intento de login fallido {"correo_intentado":"' . $correoUnico . '","ip":"10.0.0.1"} ',
            '[2026-08-11 10:00:05] local.INFO: AUDITORIA_AUTH_LOGIN: Login exitoso {"usuario_id":999,"correo":"' . $correoUnico . '","ip":"10.0.0.1"} ',
            '[2026-08-11 10:05:00] local.INFO: AUDITORIA_AUTH_LOGOUT: Logout {"usuario_id":999,"correo":"' . $correoUnico . '","ip":"10.0.0.1"} ',
        ]) . "\n");

        $response = $this->actingAs($admin)->getJson('/api/admin/logs/auditoria');
        $response->assertStatus(200);

        $entradas = collect($response->json())->where('correo', $correoUnico);

        $this->assertCount(3, $entradas);
        // Los 3 caen en la misma categoría 'autenticacion' (login fallido, login
        // exitoso y logout comparten el mismo badge azul) — se distinguen entre
        // sí por 'event_name', la descripción específica de cada uno.
        $this->assertTrue($entradas->every(fn ($e) => $e['category'] === 'autenticacion'));
        $this->assertTrue($entradas->contains(fn ($e) => $e['event_name'] === 'Intento de login fallido'));
        $this->assertTrue($entradas->contains(fn ($e) => $e['event_name'] === 'Login exitoso' && $e['usuario_id'] === 999));
        $this->assertTrue($entradas->contains(fn ($e) => $e['event_name'] === 'Logout'));
    }

    public function test_admin_ve_el_correo_resuelto_por_bd_cuando_el_log_solo_trae_usuario_id(): void
    {
        $admin = User::factory()->admin()->create();
        $cliente = User::factory()->create();

        // AUDITORIA_RESERVA/AUDITORIA_VENTA_WEB nunca guardan el correo en el
        // contexto, solo el usuario_id — el endpoint debe resolverlo por su
        // cuenta contra la BD en vez de mostrar "Desconocido" aunque el usuario
        // sí sea identificable.
        $archivo = storage_path('logs/auditoria-test-' . uniqid() . '.log');
        $this->archivosLogTemporales[] = $archivo;

        file_put_contents(
            $archivo,
            '[2026-08-11 10:10:00] local.INFO: AUDITORIA_RESERVA: Asientos bloqueados temporalmente {"usuario_id":' . $cliente->id . ',"evento_id":4,"asiento_ids":[1,2],"expira_en":"2026-08-11T10:15:00+00:00"} ' . "\n"
        );

        $response = $this->actingAs($admin)->getJson('/api/admin/logs/auditoria');
        $response->assertStatus(200);

        $entrada = collect($response->json())->firstWhere('usuario_id', $cliente->id);

        $this->assertNotNull($entrada);
        $this->assertSame('apartado_boleto', $entrada['category']);
        $this->assertSame('Asientos bloqueados temporalmente', $entrada['event_name']);
        $this->assertSame($cliente->correo, $entrada['correo']);
    }

    public function test_admin_ve_la_categoria_correcta_para_venta_pos_con_campos_de_contexto_distintos(): void
    {
        $admin = User::factory()->admin()->create();
        $vendedor = User::factory()->create(['rol' => 'vendedor']);

        // AUDITORIA_VENTA_POS usa 'vendedor_usuario_id' y 'cliente_email' — no
        // 'usuario_id'/'correo' — el endpoint debe reconocer estas claves
        // alternativas en vez de mostrar "Desconocido"/"Otro".
        $archivo = storage_path('logs/auditoria-test-' . uniqid() . '.log');
        $this->archivosLogTemporales[] = $archivo;

        file_put_contents(
            $archivo,
            '[2026-08-11 10:20:00] local.INFO: AUDITORIA_VENTA_POS: Venta en taquilla realizada {"venta_id":1,"vendedor_usuario_id":' . $vendedor->id . ',"cliente_email":"comprador.mostrador@ejemplo.com","metodo_pago":"efectivo","monto_total":300.0,"asiento_ids":[1]} ' . "\n"
        );

        $response = $this->actingAs($admin)->getJson('/api/admin/logs/auditoria');
        $response->assertStatus(200);

        $entrada = collect($response->json())->firstWhere('correo', 'comprador.mostrador@ejemplo.com');

        $this->assertNotNull($entrada);
        $this->assertSame('compra_boleto', $entrada['category']);
        $this->assertSame($vendedor->id, $entrada['usuario_id']);
    }
}
