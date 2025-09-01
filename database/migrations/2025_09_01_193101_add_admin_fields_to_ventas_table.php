<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Todos como string (nullable para no romper registros existentes)
            $table->string('mes_contr', 20)->nullable()->after('fecha_venta');
            $table->string('nro_contr_adm', 50)->nullable()->after('mes_contr');
            $table->string('nro_cliente_adm', 50)->nullable()->after('nro_contr_adm');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['mes_contr', 'nro_contr_adm', 'nro_cliente_adm']);
        });
    }
};

