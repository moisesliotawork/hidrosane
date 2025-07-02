<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('notes', function (Blueprint $table) {
            // Cambiar la longitud de nro_nota a 5 caracteres
            $table->string('nro_nota', 5)->change();
        });
    }

    public function down()
    {
        Schema::table('notes', function (Blueprint $table) {
            // Revertir a 4 caracteres
            $table->string('nro_nota', 4)->change();
        });
    }
};
