<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('tipo_medida')->nullable()->after('puntos')
                ->comment('Tipo de medida: almohada, colchon, topper');
            $table->string('medida')->nullable()->after('tipo_medida')
                ->comment('Valor de la medida específica');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['tipo_medida', 'medida']);
        });
    }
};