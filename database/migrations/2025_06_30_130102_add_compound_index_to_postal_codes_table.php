<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('postal_codes', function (Blueprint $table) {
            // Crear índice compuesto para city_id y code
            $table->index(['city_id', 'code'], 'postal_codes_city_code_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('postal_codes', function (Blueprint $table) {
            // Eliminar el índice al hacer rollback
            $table->dropIndex('postal_codes_city_code_index');
        });
    }
};