<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('boleto_evento_id')->constrained('boletos_evento')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('tipo_boleto', 50)->default('B-TIPO');
            $table->string('clave_evento', 50);
            $table->string('numero_control', 50);
            $table->string('hash_seguridad', 100);
            $table->string('token_qr')->unique();
            $table->string('seccion_pasillo', 100);
            $table->string('fila_palco', 100);
            $table->string('numero_asiento', 50);
            $table->enum('estatus', ['pendiente', 'validado', 'revocado'])->default('pendiente');
            $table->timestamp('escaneado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesos');
    }
};