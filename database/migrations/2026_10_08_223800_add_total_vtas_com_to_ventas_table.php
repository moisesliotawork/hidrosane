<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->decimal('total_vtas_com_disminuye', 8, 1)->nullable()->after('total_venta_reducida');
            $table->decimal('total_vtas_com_reparto', 8, 1)->nullable()->after('total_ventas_post_aumento');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn([
                'total_vtas_com_disminuye',
                'total_vtas_com_reparto',
            ]);
        });
    }
};
