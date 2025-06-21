<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('notes', function (Blueprint $table) {
            // Agregar el campo user_id después del id
            $table->unsignedBigInteger('user_id')->after('id');

            // Crear la relación de clave foránea
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade'); // Elimina las notas si se elimina el usuario
        });
    }

    public function down()
    {
        Schema::table('notes', function (Blueprint $table) {
            // Eliminar la relación primero
            $table->dropForeign(['user_id']);

            // Luego eliminar la columna
            $table->dropColumn('user_id');
        });
    }
};