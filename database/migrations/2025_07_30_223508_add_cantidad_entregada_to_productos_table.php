<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // ⬇️ Añadimos la columna justo después de "puntos"
            $table->unsignedInteger('cantidad_entregada')
                ->default(0)
                ->after('puntos');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('cantidad_entregada');
        });
    }
};
