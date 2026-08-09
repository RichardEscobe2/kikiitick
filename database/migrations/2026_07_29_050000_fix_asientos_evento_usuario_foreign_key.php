<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RN-05 (CLAUDE.md): la migración original de 'asientos_evento' quedó apuntando a la
     * tabla 'users' (Laravel por defecto, vacía y sin uso) en vez de 'usuarios' (tabla real
     * del dominio). El archivo de migración se corrigió en la Fase 1, pero esta base de datos
     * ya había ejecutado la versión anterior, por lo que la constraint física seguía apuntando
     * a 'users'. Esta migración corrige la constraint ya desplegada sin tocar datos existentes.
     */
    public function up(): void
    {
        Schema::table('asientos_evento', function (Blueprint $table) {
            $table->dropForeign(['reservado_por_usuario_id']);
        });

        Schema::table('asientos_evento', function (Blueprint $table) {
            $table->foreign('reservado_por_usuario_id')
                ->references('id')->on('usuarios')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // 🛡️ RN-05: el rollback debe restaurar la constraint contra 'usuarios' (la tabla real
        // del dominio), NO contra 'users' (tabla stock de Laravel, vacía y sin uso). Apuntar a
        // 'users' aquí reintroduciría exactamente el bug que esta migración corrige.
        Schema::table('asientos_evento', function (Blueprint $table) {
            $table->dropForeign(['reservado_por_usuario_id']);
        });

        Schema::table('asientos_evento', function (Blueprint $table) {
            $table->foreign('reservado_por_usuario_id')
                ->references('id')->on('usuarios')
                ->nullOnDelete();
        });
    }
};
