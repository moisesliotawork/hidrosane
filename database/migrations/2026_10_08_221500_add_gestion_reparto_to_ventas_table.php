<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->decimal('total_ventas_oferta', 3, 1)->nullable()->after('observaciones_repartidor');
            $table->string('resultado_entrega', 32)->nullable()->after('total_ventas_oferta');
            $table->decimal('total_venta_reducida', 8, 1)->nullable()->after('resultado_entrega');
            $table->decimal('monto_final_reducida', 10, 2)->nullable()->after('total_venta_reducida');
            $table->decimal('total_ventas_post_aumento', 8, 1)->nullable()->after('monto_final_reducida');
            $table->decimal('monto_final_aumento', 10, 2)->nullable()->after('total_ventas_post_aumento');
            $table->text('observaciones_gestion_reparto')->nullable()->after('monto_final_aumento');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn([
                'total_ventas_oferta',
                'resultado_entrega',
                'total_venta_reducida',
                'monto_final_reducida',
                'total_ventas_post_aumento',
                'monto_final_aumento',
                'observaciones_gestion_reparto',
            ]);
        });
    }
};
