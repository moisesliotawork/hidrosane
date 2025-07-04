<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            // Cambiar el tipo a string nullable
            $table->string('estado_terminal')
                  ->nullable()
                  ->default(null)
                  ->change();
            
            // Opcional: agregar comentario para documentación
            $table->comment("Valores posibles: null, 'nulo', 'venta', 'confirmado', 'sala'")
                  ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            // En caso de rollback, dejar como estaba
            $table->string('estado_terminal')->nullable(false)->change();
        });
    }
};