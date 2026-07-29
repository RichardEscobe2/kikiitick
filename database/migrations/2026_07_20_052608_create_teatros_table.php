<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teatros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('nombre');
            $table->string('ubicacion');
            $table->integer('capacidad_total');
            
            // Matriz física global del teatro
            $table->integer('filas_totales')->default(10);
            $table->integer('asientos_por_fila')->default(20);
            $table->json('pasillos_slots')->nullable();
            $table->string('posicion_escenario', 50)->default('arriba');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teatros');
    }
};