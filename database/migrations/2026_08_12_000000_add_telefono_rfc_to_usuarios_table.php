<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos de contacto opcionales del perfil general de usuario (PATCH
     * /api/user/perfil) — distintos de solicitudes_organizador.telefono_contacto/
     * rfc, que son la propuesta de contacto de UNA solicitud de organizador
     * específica. Aquí es el dato de contacto de la cuenta en sí, para
     * cualquier rol.
     */
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('telefono', 10)->nullable()->after('correo');
            $table->string('rfc', 13)->nullable()->after('telefono');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['telefono', 'rfc']);
        });
    }
};
