<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('empleado_id', 2)
                ->nullable()
                ->unique()
                ->after('id') // Opcional: define la posición de la columna
                ->comment('Código de dos dígitos para el usuario');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('empleado_id');
        });
    }
};