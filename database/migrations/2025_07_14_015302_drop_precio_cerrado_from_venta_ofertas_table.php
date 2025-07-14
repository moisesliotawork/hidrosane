<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::table('venta_ofertas', function (Blueprint $table) {
            // -- Eliminar la columna --
            if (Schema::hasColumn('venta_ofertas', 'precio_cerrado')) {
                $table->dropColumn('precio_cerrado');
            }
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table('venta_ofertas', function (Blueprint $table) {
            // -- Volver a crear la columna (por si necesitas revertir) --
            $table->decimal('precio_cerrado', 10, 2)->after('oferta_id');
        });
    }
};
