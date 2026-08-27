<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_pdf_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 10)->nullable();
            $table->string('file_path', 500);
            $table->timestamps();

            $table->index(['venta_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_pdf_downloads');
    }
};
