<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // 1. Eliminar el índice único existente (si existe)
            $table->dropUnique(['empleado_id']);

            // 2. Modificar la columna y volver a agregar el UNIQUE
            $table->string('empleado_id', 3)
                ->nullable()
                ->unique()
                ->comment('Código de tres dígitos para el usuario')
                ->change();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Revertir: eliminar el nuevo índice y restaurar el anterior
            $table->dropUnique(['empleado_id']);

            $table->string('empleado_id', 2)
                ->nullable()
                ->unique()
                ->comment('Código de dos dígitos para el usuario')
                ->change();
        });
    }
};