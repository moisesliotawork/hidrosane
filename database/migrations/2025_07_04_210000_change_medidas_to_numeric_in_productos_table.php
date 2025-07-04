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
        Schema::table('productos', function (Blueprint $table) {
            // Cambiar medida1 a decimal (10,2) nullable
            $table->decimal('medida1', 10, 2)
                  ->nullable()
                  ->default(null)
                  ->change();
                  
            // Cambiar medida2 a decimal (10,2) nullable
            $table->decimal('medida2', 10, 2)
                  ->nullable()
                  ->default(null)
                  ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // Revertir a string nullable
            $table->string('medida1')
                  ->nullable()
                  ->default(null)
                  ->change();
                  
            $table->string('medida2')
                  ->nullable()
                  ->default(null)
                  ->change();
        });
    }
};