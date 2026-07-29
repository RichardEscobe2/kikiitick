<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar indicador numérico a zonas_teatro
        Schema::table('zonas_teatro', function (Blueprint $table) {
            if (!Schema::hasColumn('zonas_teatro', 'es_numerada')) {
                $table->boolean('es_numerada')->default(true)->after('capacidad_asientos');
            }
        });

        // 2. Crear tabla de Asientos físicos vinculada al Teatro
        Schema::create('asientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teatro_id')->constrained('teatros')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('zona_teatro_id')->nullable()->constrained('zonas_teatro')->cascadeOnDelete()->cascadeOnUpdate();
            
            $table->string('fila', 10);              // Ej: 'A', 'B', '1', '2'
            $table->integer('numero');               // Ej: 1, 2, 3
            $table->string('codigo', 20);            // Ej: 'A-1', 'B-12'
            $table->integer('slot_index');           // Posición física en el render grid
            $table->enum('tipo', ['asiento', 'pasillo'])->default('asiento');
            $table->timestamps();

            $table->unique(['teatro_id', 'fila', 'numero'], 'asientos_teatro_fila_numero_unique');
        });

        // 3. Crear tabla de Estado del Asiento por Evento (Sin cambios)
        Schema::create('asientos_evento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('asiento_id')->constrained('asientos')->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('estado', ['disponible', 'reservado', 'bloqueado', 'vendido'])->default('disponible');
            $table->foreignId('reservado_por_usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('reservado_hasta')->nullable();
            $table->timestamps();

            $table->unique(['evento_id', 'asiento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asientos_evento');
        Schema::dropIfExists('asientos');

        Schema::table('zonas_teatro', function (Blueprint $table) {
            $table->dropColumn(['es_numerada']);
        });
    }
};