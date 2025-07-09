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
        Schema::create('tipos_medida', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('unidad'); // Ej: 'kg', 'ml', 'cm'
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_medida');
    }
};
