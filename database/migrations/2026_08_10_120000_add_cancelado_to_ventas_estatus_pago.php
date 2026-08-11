<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MySQL (todo entorno real) conserva el ALTER crudo de siempre. SQLite (solo la
     * suite de tests automatizados) no entiende "MODIFY"; Schema::table()->enum()
     * ->change() es la ruta equivalente — funciona nativamente desde Laravel 11 sin
     * depender de doctrine/dbal (verificado: no hace falta el paquete).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('ventas', function (Blueprint $table) {
                $table->enum('estatus_pago', ['pendiente', 'pagado', 'fallido', 'cancelado'])->default('pendiente')->change();
            });
        } else {
            DB::statement("ALTER TABLE ventas MODIFY estatus_pago ENUM('pendiente', 'pagado', 'fallido', 'cancelado') NOT NULL DEFAULT 'pendiente'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('ventas', function (Blueprint $table) {
                $table->enum('estatus_pago', ['pendiente', 'pagado', 'fallido'])->default('pendiente')->change();
            });
        } else {
            DB::statement("ALTER TABLE ventas MODIFY estatus_pago ENUM('pendiente', 'pagado', 'fallido') NOT NULL DEFAULT 'pendiente'");
        }
    }
};
