<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teleoperator_monthly_stats', function (Blueprint $table) {
            $table->id();

            // FK a users.id (teleoperators)
            $table->foreignId('teleoperator_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->smallInteger('year');   // 2025
            $table->tinyInteger('month');   // 1..12
            $table->tinyInteger('quarter'); // 1..4

            // Métricas base
            $table->unsignedInteger('producidas')->default(0);
            $table->unsignedInteger('confirmadas')->default(0);
            $table->unsignedInteger('ventas')->default(0);
            $table->unsignedInteger('nulas')->default(0);

            // Campos calculados en BD
            // vta_conf = ventas + confirmadas
            $table->integer('vta_conf')
                ->storedAs('(`confirmadas` + `ventas`)');

            // pct_conf = ((ventas + confirmadas) / producidas) * 100
            // Si producidas = 0 → NULL
            $table->decimal('pct_conf', 6, 2)
                ->storedAs('CASE 
                    WHEN `producidas` = 0 THEN NULL
                    ELSE ((`confirmadas` + `ventas`) / `producidas`) * 100
                END');

            $table->timestamps();

            // Un registro por teleoperadora / año / mes
            $table->unique(['teleoperator_id', 'year', 'month'], 'teleop_month_unique');

            // Índices útiles para BI
            $table->index(['year', 'month']);
            $table->index('teleoperator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teleoperator_monthly_stats');
    }
};

