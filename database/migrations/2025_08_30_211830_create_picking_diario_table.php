<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('picking_diario', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->index();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->unsignedInteger('cantidad_total')->default(0);
            $table->boolean('entregado')->default(false)->index();
            $table->timestamp('entregado_at')->nullable();
            $table->foreignId('entregado_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['fecha','producto_id']); // una fila por (día, producto)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('picking_diario');
    }
};

