<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // decimal(10,2) → hasta 99 999 999,99 €
            $table->decimal('cuota_mensual', 10, 2)
                ->after('importe_total')
                ->nullable();          // por si alguna venta no lleva financiación
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('cuota_mensual');
        });
    }
};
