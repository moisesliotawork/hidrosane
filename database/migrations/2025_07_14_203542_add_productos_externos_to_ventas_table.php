<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Agrega la columna productos_externos a ventas.
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // TEXT: guarda una lista separada por comas, permite null
            $table->text('productos_externos')->nullable()->after('interes_art');
        });
    }

    /**
     * Revierte el cambio.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('productos_externos');
        });
    }
};
