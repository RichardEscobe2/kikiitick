<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete()->cascadeOnUpdate();
            $table->decimal('monto_neto', 10, 2);
            $table->decimal('total_comisiones', 10, 2);
            $table->decimal('monto_total', 10, 2);
            $table->enum('estatus_pago', ['pendiente', 'pagado', 'fallido'])->default('pendiente');
            $table->timestamp('fecha_venta')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};