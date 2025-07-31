<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Elimina la columna `cantidad_entregada` de la tabla `productos`.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // Si la columna existe la eliminamos
            if (Schema::hasColumn('productos', 'cantidad_entregada')) {
                $table->dropColumn('cantidad_entregada');
            }
        });
    }

    /**
     * Vuelve a crear la columna si se hace rollback.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->unsignedInteger('cantidad_entregada')->nullable()->after('puntos');
        });
    }
};
