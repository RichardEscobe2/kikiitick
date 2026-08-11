<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Módulo 1/5 — OWASP A01 (Broken Access Control): un admin nunca debe poder
 * auto-eliminarse ni eliminar a otro admin. Verificado tanto el rechazo
 * (403 + cuenta intacta) como el registro en el canal de auditoría.
 */
class AdminProteccionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Igual que en AuditoriaTransaccionalTest: lee el contenido REAL agregado
     * al log de auditoría del día durante $accion(), sin mockear Log::channel().
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

    public function test_un_admin_no_puede_eliminar_su_propia_cuenta(): void
    {
        $admin = User::factory()->admin()->create();

        $nuevo = $this->contenidoNuevoDeAuditoria(function () use ($admin) {
            $this->actingAs($admin)
                ->deleteJson("/api/admin/usuarios/{$admin->id}")
                ->assertStatus(403)
                ->assertJson(['message' => 'No puedes eliminar tu propia cuenta de administrador.']);
        });

        $this->assertDatabaseHas('usuarios', ['id' => $admin->id, 'deleted_at' => null]);

        $this->assertStringContainsString('AUDITORIA_SEGURIDAD: Intento no autorizado de eliminación de cuenta admin', $nuevo);
        $this->assertStringContainsString('"attempted_by":' . $admin->id, $nuevo);
        $this->assertStringContainsString('"target_user":' . $admin->id, $nuevo);
        $this->assertStringContainsString('"motivo":"auto_eliminacion"', $nuevo);
    }

    public function test_un_admin_no_puede_eliminar_a_otro_admin(): void
    {
        $adminSolicitante = User::factory()->admin()->create();
        $adminObjetivo = User::factory()->admin()->create();

        $nuevo = $this->contenidoNuevoDeAuditoria(function () use ($adminSolicitante, $adminObjetivo) {
            $this->actingAs($adminSolicitante)
                ->deleteJson("/api/admin/usuarios/{$adminObjetivo->id}")
                ->assertStatus(403)
                ->assertJson(['message' => 'Las cuentas de administrador son protegidas y no pueden ser eliminadas.']);
        });

        $this->assertDatabaseHas('usuarios', ['id' => $adminObjetivo->id, 'deleted_at' => null]);

        $this->assertStringContainsString('AUDITORIA_SEGURIDAD: Intento no autorizado de eliminación de cuenta admin', $nuevo);
        $this->assertStringContainsString('"attempted_by":' . $adminSolicitante->id, $nuevo);
        $this->assertStringContainsString('"target_user":' . $adminObjetivo->id, $nuevo);
        $this->assertStringContainsString('"motivo":"cuenta_admin_protegida"', $nuevo);
    }

    public function test_un_admin_si_puede_eliminar_a_un_usuario_no_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $cliente = User::factory()->create(['rol' => 'cliente']);

        $this->actingAs($admin)
            ->deleteJson("/api/admin/usuarios/{$cliente->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('usuarios', ['id' => $cliente->id]);
    }

    public function test_un_no_admin_no_puede_acceder_al_endpoint_de_eliminar(): void
    {
        $cliente = User::factory()->create();
        $otro = User::factory()->create();

        $this->actingAs($cliente)
            ->deleteJson("/api/admin/usuarios/{$otro->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('usuarios', ['id' => $otro->id, 'deleted_at' => null]);
    }
}
