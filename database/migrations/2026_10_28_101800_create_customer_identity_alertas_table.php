<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_identity_alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->string('nro_contr_adm')->nullable()->index();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('coincidencia_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('nombre_antes')->nullable();
            $table->string('dni_antes')->nullable();
            $table->string('nombre_despues')->nullable();
            $table->string('dni_despues')->nullable();
            $table->string('coincidencia_nombre')->nullable();
            $table->string('coincidencia_dni')->nullable();
            $table->string('via', 20)->nullable();
            $table->foreignId('caused_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('caused_by_label')->nullable();
            $table->timestamp('ocurrido_at')->nullable()->index();
            $table->timestamp('acknowledged_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_identity_alertas');
    }
};
