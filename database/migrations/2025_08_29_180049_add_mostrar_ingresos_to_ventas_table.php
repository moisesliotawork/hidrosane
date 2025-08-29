<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->boolean('mostrar_ingresos')
                ->default(true)
                ->after('crema'); // ajusta si "crema" no existe o cambia el orden
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('mostrar_ingresos');
        });
    }
};
