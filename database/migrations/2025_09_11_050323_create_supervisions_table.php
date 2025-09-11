<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisiones', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('supervisor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('supervisado_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('author_id')   // quién creó la supervisión
                ->constrained('users')
                ->restrictOnDelete();

            // Fechas de vigencia
            $table->date('start_date');        // fecha de inicio
            $table->date('end_date')->nullable(); // fecha fin (nullable si aún está vigente)

            // Timestamps estándar
            $table->timestamps();

            // Índices
            $table->index(['supervisor_id', 'supervisado_id']);
            $table->index('author_id');

            //evitar duplicados exactos activos:
            $table->unique(['supervisor_id', 'supervisado_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisiones');
    }
};
