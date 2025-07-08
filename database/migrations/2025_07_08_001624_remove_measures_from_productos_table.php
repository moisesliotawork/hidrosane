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
            $table->dropColumn(['medida1', 'medida2', 'nombre_medida1', 'nombre_medida2']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->integer('medida1')->nullable();
            $table->integer('medida2')->nullable();
            $table->string('nombre_medida1')->nullable();
            $table->string('nombre_medida2')->nullable();
        });
    }
};