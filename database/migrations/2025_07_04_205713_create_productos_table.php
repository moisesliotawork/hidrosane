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
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable(false); // Obligatorio
            $table->string('medida1')->nullable();    // Opcional
            $table->string('medida2')->nullable();    // Opcional
            $table->integer('puntos')->nullable(false); // Obligatorio
            $table->timestamps(); // created_at y updated_at

            // Opcional: índice para búsquedas por nombre
            $table->index('nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};