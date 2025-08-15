<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Usamos string (backed enum en PHP) + índice y valor por defecto
            $table->string('estado_venta')
                ->default('en_revision')   // EstadoVenta::EN_REVISION->value
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('estado_venta');
        });
    }
};
