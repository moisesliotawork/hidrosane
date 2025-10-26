<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transaction_venta', function (Blueprint $table) {
            $table->id();

            // Ambos IDs como foráneas a ventas.id (int/bigint según tu PK)
            $table->unsignedBigInteger('id_contrato');
            $table->unsignedBigInteger('id_contrato_asoc');

            // Índices y FKs
            $table->foreign('id_contrato')
                ->references('id')->on('ventas')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('id_contrato_asoc')
                ->references('id')->on('ventas')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            // Evita duplicar exactamente el mismo par (A,B)
            $table->unique(['id_contrato', 'id_contrato_asoc'], 'trx_venta_pair_unique');

            // Fechas
            $table->timestamps();    // created_at, updated_at
            $table->softDeletes();   // deleted_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_venta');
    }
};
