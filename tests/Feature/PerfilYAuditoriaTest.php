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
            '[2026-08-11 10:00:00] local.WARNING: Intento de login fallido {"correo_intentado":"' . $correoUnico . '","ip":"10.0.0.1"} ',
            '[2026-08-11 10:00:05] local.INFO: Login exitoso {"usuario_id":999,"correo":"' . $correoUnico . '","ip":"10.0.0.1"} ',
            '[2026-08-11 10:05:00] local.INFO: Logout {"usuario_id":999,"correo":"' . $correoUnico . '","ip":"10.0.0.1"} ',
        ]) . "\n");

        $response = $this->actingAs($admin)->getJson('/api/admin/logs/auditoria');
        $response->assertStatus(200);

        $entradas = collect($response->json())->where('correo', $correoUnico);

        $this->assertCount(3, $entradas);
        $this->assertTrue($entradas->contains(fn ($e) => $e['event_type'] === 'login_fallido'));
        $this->assertTrue($entradas->contains(fn ($e) => $e['event_type'] === 'login_exitoso' && $e['usuario_id'] === 999));
        $this->assertTrue($entradas->contains(fn ($e) => $e['event_type'] === 'logout'));
    }
}
