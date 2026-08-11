<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Blueprint::enum()->change() requiere doctrine/dbal para MODIFY COLUMN en MySQL,
     * así que se altera el enum con SQL crudo — mismo patrón que el resto del proyecto
     * usa para cambios de columna no soportados por el builder (ver migraciones previas
     * de este directorio).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE ventas MODIFY estatus_pago ENUM('pendiente', 'pagado', 'fallido', 'cancelado') NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ventas MODIFY estatus_pago ENUM('pendiente', 'pagado', 'fallido') NOT NULL DEFAULT 'pendiente'");
    }
};
