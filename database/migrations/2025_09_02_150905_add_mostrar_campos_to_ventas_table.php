<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Colócalos donde prefieras; "after" es opcional
            $table->boolean('mostrar_tipo_vivienda')->default(true)->after('mostrar_ingresos');
            $table->boolean('mostrar_situacion_lab')->default(true)->after('mostrar_tipo_vivienda');
        });

        // Backfill para registros existentes (evita NULLs si tu base ya tiene filas)
        DB::table('ventas')
            ->whereNull('mostrar_tipo_vivienda')
            ->update(['mostrar_tipo_vivienda' => true]);

        DB::table('ventas')
            ->whereNull('mostrar_situacion_lab')
            ->update(['mostrar_situacion_lab' => true]);
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['mostrar_tipo_vivienda', 'mostrar_situacion_lab']);
        });
    }
};
