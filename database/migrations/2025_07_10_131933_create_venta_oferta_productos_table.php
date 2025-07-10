<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_oferta_productos', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->unsignedBigInteger('venta_oferta_id');
            $table->unsignedBigInteger('producto_id');

            // Detalle de la línea
            $table->unsignedSmallInteger('cantidad');               // 0-65 535
            $table->decimal('precio_unitario', 10, 2);
            $table->unsignedTinyInteger('puntos_linea')->nullable();

            $table->timestamps();

            /* ---------- Claves foráneas ---------- */
            $table->foreign('venta_oferta_id')
                  ->references('id')->on('venta_ofertas')
                  ->cascadeOnDelete();      // si se elimina la oferta-venta, caen sus líneas

            $table->foreign('producto_id')
                  ->references('id')->on('productos')
                  ->restrictOnDelete();     // evita borrar producto si está usado
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_oferta_productos');
    }
};
