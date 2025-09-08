<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('note_null_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comercial_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason'); // motivo de nulidad escrito por el comercial
            $table->timestamps();   // created_at y updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_null_reasons');
    }
};

