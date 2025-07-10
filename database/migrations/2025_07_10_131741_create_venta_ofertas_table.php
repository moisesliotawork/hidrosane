<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_ofertas', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->unsignedBigInteger('venta_id');
            $table->unsignedBigInteger('oferta_id');   // “molde”

            // Datos variables
            $table->decimal('precio_cerrado', 10, 2);
            $table->unsignedTinyInteger('puntos');

            $table->timestamps();

            /* ---------- Claves foráneas ---------- */
            $table->foreign('venta_id')
                  ->references('id')->on('ventas')
                  ->cascadeOnDelete();

            $table->foreign('oferta_id')
                  ->references('id')->on('ofertas')
                  ->restrictOnDelete();   // evita borrar oferta si está usada
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_ofertas');
    }
};
