<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos_mes_variacion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->string('mes_key', 7)->index();
            $table->string('estado', 32); // soft_delete | nuevo | restaurado | borrado
            $table->string('nro_contr_adm', 50)->nullable();
            $table->string('cliente_nombre')->nullable();
            $table->string('dni', 32)->nullable();
            $table->timestamps();

            $table->unique(['venta_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos_mes_variacion_items');
    }
};
