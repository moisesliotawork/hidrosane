<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->string('fuente')
                ->nullable()
                ->after('comercial_id')
                ->comment('Fuente de origen de la nota');
        });
    }

    public function down()
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn('fuente');
        });
    }
};