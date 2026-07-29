<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas_teatro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teatro_id')->constrained('teatros')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('nombre_zona', 100);
            $table->enum('nivel_proximidad', ['0x','1x', '2x', '3x', '4x']);
            $table->integer('capacidad_asientos');
            
            // Rangos de filas asignadas a la zona
            $table->string('fila_inicio', 5)->nullable();
            $table->string('fila_fin', 5)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zonas_teatro');
    }
};