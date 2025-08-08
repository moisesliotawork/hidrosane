<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('venta_oferta_productos', function (Blueprint $table) {
            // Si prefieres evitar ENUM por futuras ampliaciones, usa string(20)
            $table->enum('vendido_por', ['comercial', 'repartidor'])
                ->default('comercial')
                ->after('puntos_linea');

            $table->string('vendido_por', 20)->default('comercial')->change();
        });

        // Backfill por si existieran filas antiguas con null
        DB::table('venta_oferta_productos')
            ->whereNull('vendido_por')
            ->update(['vendido_por' => 'comercial']);
    }

    public function down(): void
    {
        Schema::table('venta_oferta_productos', function (Blueprint $table) {
            $table->dropColumn('vendido_por');
        });
    }
};
