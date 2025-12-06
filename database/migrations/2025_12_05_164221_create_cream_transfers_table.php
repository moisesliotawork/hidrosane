<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cream_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_comercial_id')->constrained('users');
            $table->foreignId('to_comercial_id')->constrained('users');
            $table->date('date');
            $table->unsignedInteger('amount');
            $table->string('status')->default('pending'); // pending, accepted, rejected
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cream_transfers');
    }
};

