<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Ventas del repartidor
            $table->unsignedInteger('vta_rep')->default(0);
            // Ventas excepcionales hechas por el repartidor
            $table->unsignedInteger('vta_esp')->default(0);
            // Ventas acumuladas (rep + esp) — la recalcularemos en el modelo
            $table->unsignedInteger('vta_ac')->default(0);

            // Comisiones (decimales)
            // COM VENTA: comisión por la venta hecha por el repartidor
            $table->decimal('com_venta', 10, 2)->default(0);
            // COMentr: comisión por entregar un pedido
            $table->decimal('com_entrega', 10, 2)->default(0);
            // ConPago: comisión por reducir el número de cuotas a la venta del comercial
            $table->decimal('com_conpago', 10, 2)->default(0);

            // Puntos
            // PAS c: puntos que se pasó el comercial en el total de la oferta
            $table->unsignedInteger('pas_comercial')->default(0);
            // PASR: puntos que se pasó el repartidor
            $table->unsignedInteger('pas_repartidor')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn([
                'vta_rep',
                'vta_esp',
                'vta_ac',
                'com_venta',
                'com_entrega',
                'com_conpago',
                'pas_comercial',
                'pas_repartidor',
            ]);
        });
    }
};
