<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_fecha_cambios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->string('nro_contr_adm')->nullable()->index();
            $table->string('cliente_nombre')->nullable();
            $table->date('fecha_anterior')->nullable();
            $table->date('fecha_nueva')->nullable();
            $table->foreignId('caused_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('caused_by_label')->nullable();
            $table->timestamp('ocurrido_at')->nullable()->index();
            $table->timestamp('acknowledged_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_fecha_cambios');
    }
};
