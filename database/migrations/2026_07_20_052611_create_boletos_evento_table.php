<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boletos_evento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('zona_teatro_id')->constrained('zonas_teatro')->cascadeOnDelete()->cascadeOnUpdate();
            $table->decimal('precio_base', 10, 2);
            $table->integer('stock_disponible');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boletos_evento');
    }
};