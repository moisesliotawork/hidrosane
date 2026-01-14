<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('historial_ausentes', function (Blueprint $table) {
            $table->text('observacion')->nullable()->change(); // soporta textos largos
        });
    }

    public function down(): void
    {
        Schema::table('historial_ausentes', function (Blueprint $table) {
            $table->string('observacion', 255)->nullable()->change();
        });
    }
};

