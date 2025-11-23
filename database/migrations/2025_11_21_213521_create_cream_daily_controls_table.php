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
        Schema::create('cream_daily_controls', function (Blueprint $table) {
            $table->id();

            // Comercial al que se le asignan las cremas
            $table->foreignId('comercial_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Día al que corresponde el control
            $table->date('date');

            // Cantidad de cremas asignadas ese día
            $table->unsignedInteger('assigned')->default(0);

            // Cantidad de cremas entregadas ese día (ventas con crema = true)
            $table->unsignedInteger('delivered')->default(0);

            // Total por día = assigned - delivered
            $table->unsignedInteger('remaining')->default(0);

            $table->timestamps();

            // Evita duplicar registros del mismo comercial en la misma fecha
            $table->unique(['comercial_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cream_daily_controls');
    }
};
