<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices compuestos para los hot paths de disponibilidad de asientos, stock de
     * boletos, listados de eventos y estatus de pago. Cada FK individual ya tiene índice
     * implícito vía foreignId()->constrained(), pero ninguna combinación cubría las
     * consultas reales del sistema (ej. "asientos disponibles de este evento",
     * "reservas expiradas", "eventos activos de este recinto").
     */
    public function up(): void
    {
        Schema::table('asientos_evento', function (Blueprint $table) {
            $table->index(['evento_id', 'estado'], 'asientos_evento_evento_id_estado_index');
            $table->index(['estado', 'reservado_hasta'], 'asientos_evento_estado_reservado_hasta_index');
        });

        Schema::table('boletos_evento', function (Blueprint $table) {
            $table->index(['evento_id', 'stock_disponible'], 'boletos_evento_evento_id_stock_disponible_index');
        });

        Schema::table('eventos', function (Blueprint $table) {
            $table->index(['teatro_id', 'estatus'], 'eventos_teatro_id_estatus_index');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->index(['usuario_id', 'estatus_pago'], 'ventas_usuario_id_estatus_pago_index');
        });
    }

    public function down(): void
    {
        Schema::table('asientos_evento', function (Blueprint $table) {
            $table->dropIndex('asientos_evento_evento_id_estado_index');
            $table->dropIndex('asientos_evento_estado_reservado_hasta_index');
        });

        Schema::table('boletos_evento', function (Blueprint $table) {
            $table->dropIndex('boletos_evento_evento_id_stock_disponible_index');
        });

        Schema::table('eventos', function (Blueprint $table) {
            $table->dropIndex('eventos_teatro_id_estatus_index');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex('ventas_usuario_id_estatus_pago_index');
        });
    }
};
