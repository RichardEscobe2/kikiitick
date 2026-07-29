<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            // Solo agregamos la expiración justo después de codigo_verificacion
            $table->timestamp('codigo_expira_en')->nullable()->after('codigo_verificacion');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('codigo_expira_en');
        });
    }
};