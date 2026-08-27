<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos_mes_baselines', function (Blueprint $table) {
            $table->id();
            $table->string('mes_key', 7)->unique(); // YYYY-MM
            $table->unsignedInteger('baseline_total')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos_mes_baselines');
    }
};
