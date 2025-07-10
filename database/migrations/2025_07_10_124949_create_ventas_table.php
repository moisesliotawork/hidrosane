<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();

            // Relaciones principales
            $table->unsignedBigInteger('note_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('comercial_id');
            $table->unsignedBigInteger('companion_id')->nullable();

            // Datos de la venta
            $table->dateTime('fecha_venta');
            $table->decimal('importe_total', 10, 2);
            $table->unsignedTinyInteger('num_cuotas');           // 1 ó 6-39

            // Campos adicionales
            $table->string('accesorio_entregado', 100)->nullable();
            $table->string('motivo_venta', 100)->nullable();
            $table->string('motivo_horario', 100)->nullable();
            $table->boolean('interes_art')->default(false);
            $table->text('interes_art_detalle')->nullable();
            $table->text('observaciones_repartidor')->nullable();

            // Estado de flujo
            $table->enum('status', ['BORRADOR', 'ENVIADA', 'VALIDADA', 'RECHAZADA'])
                ->default('BORRADOR');

            $table->timestamps();

            /* ---------- Claves foráneas ---------- */
            $table->foreign('note_id')
                ->references('id')->on('notes')
                ->cascadeOnDelete();

            $table->foreign('customer_id')
                ->references('id')->on('customers')
                ->cascadeOnDelete();

            $table->foreign('comercial_id')
                ->references('id')->on('users')
                ->restrictOnDelete();

            $table->foreign('companion_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
