<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Agrega la columna `cantidad_entregada` con valor por defecto 0.
     */
    public function up(): void
    {
        Schema::table('venta_oferta_productos', function (Blueprint $table) {
            if (!Schema::hasColumn('venta_oferta_productos', 'cantidad_entregada')) {
                $table->unsignedInteger('cantidad_entregada')
                    ->default(0)
                    ->after('cantidad');
            }
        });
    }

    /**
     * Elimina la columna en caso de rollback.
     */
    public function down(): void
    {
        Schema::table('venta_oferta_productos', function (Blueprint $table) {
            $table->dropColumn('cantidad_entregada');
        });
    }
};
