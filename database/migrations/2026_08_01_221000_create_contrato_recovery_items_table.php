<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_recovery_items', function (Blueprint $table) {
            $table->id();
            $table->string('status', 32)->default('draft')->index();
            $table->json('documents')->nullable();
            $table->json('extracted_json')->nullable();
            $table->json('reviewed_json')->nullable();
            $table->string('dni')->nullable()->index();
            $table->string('nro_contr_adm')->nullable()->index();
            $table->string('cliente_nombre')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->foreignId('comercial_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_recovery_items');
    }
};
