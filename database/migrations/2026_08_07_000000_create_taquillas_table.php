<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 🛡️ recinto_id -> teatros.id (NO una tabla "recintos", que no existe en este
     * proyecto): "recinto" es solo el sinónimo que usan los comentarios del código
     * para "teatro" (ver User::teatros()) — el modelo real de venue ya es Teatro,
     * con sus zonas/asientos/eventos. Crear una tabla "recintos" aparte duplicaría
     * esa entidad.
     */
    public function up(): void
    {
        Schema::create('taquillas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teatro_id')->constrained('teatros')->cascadeOnDelete();
            $table->string('nombre');
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taquillas');
    }
};
