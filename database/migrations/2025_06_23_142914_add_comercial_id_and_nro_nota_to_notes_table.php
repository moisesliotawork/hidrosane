<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up()
    {
        Schema::table('notes', function (Blueprint $table) {
            // Agregar comercial_id (nullable)
            $table->unsignedBigInteger('comercial_id')->nullable();
            $table->foreign('comercial_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Agregar nro_nota (único de 4 dígitos)
            $table->string('nro_nota', 4)->unique()->nullable();
        });
    }

    public function down()
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropForeign(['comercial_id']);
            $table->dropColumn('comercial_id');
            $table->dropColumn('nro_nota');
        });
    }
};
