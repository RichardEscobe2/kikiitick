<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teatro_id')->constrained('teatros')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->string('imagen_url');
            $table->string('categoria', 50);
            $table->dateTime('fecha_hora');
            $table->decimal('comision_fija_empresa', 10, 2)->default(0.00);
            $table->enum('estatus', ['borrador', 'activo', 'finalizado'])->default('borrador');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};