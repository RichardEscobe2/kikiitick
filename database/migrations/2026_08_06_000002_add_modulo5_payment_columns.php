<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Módulo 5 (Pagos): agrega el rol 'vendedor' (RN-09/RF-10, taquilla física),
     * enlaza asientos_evento con la Venta que los compró (evita reconstrucción frágil
     * de asientos a partir de datos denormalizados de Acceso al confirmar un pago), y
     * agrega metodo_pago / vendido_por_usuario_id a ventas para distinguir compras
     * en línea (Mercado Pago) de ventas de taquilla procesadas por personal.
     */
    public function up(): void
    {
        // 🛡️ MySQL (todo entorno real: nativo/Docker/producción) conserva EXACTAMENTE
        // el mismo ALTER crudo de siempre — comportamiento sin cambios. SQLite (solo
        // usado por la suite de tests automatizados vía phpunit.xml) no entiende
        // "MODIFY"; Schema::table()->enum()->change() es la ruta equivalente que sí
        // soporta nativamente desde Laravel 11, sin depender de doctrine/dbal.
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->enum('rol', ['admin', 'organizador', 'cliente', 'vendedor'])->default('cliente')->change();
            });
        } else {
            DB::statement("ALTER TABLE usuarios MODIFY rol ENUM('admin','organizador','cliente','vendedor') NOT NULL DEFAULT 'cliente'");
        }

        Schema::table('asientos_evento', function (Blueprint $table) {
            $table->foreignId('venta_id')->nullable()->after('reservado_por_usuario_id')
                ->constrained('ventas')->nullOnDelete();
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->string('metodo_pago')->nullable()->after('estatus_pago');
            $table->foreignId('vendido_por_usuario_id')->nullable()->after('usuario_id')
                ->constrained('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendido_por_usuario_id');
            $table->dropColumn('metodo_pago');
        });

        Schema::table('asientos_evento', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venta_id');
        });

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->enum('rol', ['admin', 'organizador', 'cliente'])->default('cliente')->change();
            });
        } else {
            DB::statement("ALTER TABLE usuarios MODIFY rol ENUM('admin','organizador','cliente') NOT NULL DEFAULT 'cliente'");
        }
    }
};
