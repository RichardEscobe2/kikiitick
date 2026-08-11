<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos de contacto/recinto que un 'cliente' propone al pedir convertirse en
     * organizador vía POST /api/solicitud-organizador (AuthController). Separada
     * de `teatros` a propósito: el recinto real solo se crea al aprobar la
     * solicitud (AdminController::aprobarOrganizador) — antes de eso es apenas
     * una propuesta que el admin puede revisar y rechazar.
     *
     * `usuario_id` es único: un usuario tiene como máximo UNA solicitud vigente;
     * reenviar (tras 'rechazado') sobreescribe la anterior en vez de acumular
     * historial, igual que el resto del esquema de este proyecto no versiona
     * estados intermedios.
     */
    public function up(): void
    {
        Schema::create('solicitudes_organizador', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->unique()->constrained('usuarios')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('recinto_nombre');
            $table->string('recinto_direccion');
            $table->unsignedInteger('recinto_capacidad');
            $table->string('telefono_contacto', 20);
            $table->string('rfc', 20)->nullable();
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_organizador');
    }
};
