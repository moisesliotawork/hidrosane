<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();

            // Personal information fields
            $table->string('first_names');
            $table->string('last_names');
            $table->string('phone');
            $table->string('secondary_phone')->nullable();
            $table->string('email');
            $table->string('postal_code');
            $table->string('primary_address');
            $table->string('secondary_address')->nullable();
            $table->string('parish')->nullable();

            // Commercial management fields
            $table->string('status');
            $table->text('observations')->nullable();

            // Reprogramming fields
            $table->date('reschedule_date')->nullable();
            $table->text('reschedule_notes')->nullable();

            // Visit fields
            $table->date('visit_date')->nullable();
            $table->string('visit_schedule')->nullable();

            // Timestamps
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};