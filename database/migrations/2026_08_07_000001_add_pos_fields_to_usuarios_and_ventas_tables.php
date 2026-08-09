<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 🛡️ RN-05: toda FK que apunte a usuarios DEBE referenciar la tabla 'usuarios'
     * (no 'users' — esa es la tabla del esqueleto de Laravel, sin uso en la app;
     * el modelo User real está mapeado a 'usuarios' vía $table).
     */
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            // Vincula cuentas de cajero/vendedor con el usuario organizador que las
            // emplea. Autorreferencial -> constrained() no puede adivinar la tabla
            // por el nombre de columna, se indica explícitamente.
            $table->foreignId('organizador_padre_id')
                ->nullable()
                ->after('rol')
                ->constrained('usuarios')
                ->nullOnDelete();

            $table->foreignId('taquilla_id')
                ->nullable()
                ->after('organizador_padre_id')
                ->constrained('taquillas')
                ->nullOnDelete();
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('taquilla_id')
                ->nullable()
                ->after('vendido_por_usuario_id')
                ->constrained('taquillas')
                ->nullOnDelete();

            // ⚠️ Se agrega tal como se solicitó, pero nótese que ventas.vendido_por_usuario_id
            // ya registra qué usuario procesó una venta de taquilla (ver
            // CompraService::comprarEnTaquilla) — este campo se solapa conceptualmente
            // con ese. Se deja para que una fase posterior decida si se consolidan.
            $table->foreignId('vendedor_id')
                ->nullable()
                ->after('taquilla_id')
                ->constrained('usuarios')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendedor_id');
            $table->dropConstrainedForeignId('taquilla_id');
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('taquilla_id');
            $table->dropConstrainedForeignId('organizador_padre_id');
        });
    }
};
