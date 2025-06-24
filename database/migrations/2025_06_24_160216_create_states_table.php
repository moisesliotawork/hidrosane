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
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->string('title');
            $table->string('iso', 2);
            $table->foreignId('country_id')->constrained('countries')->onDelete('cascade');
            $table->timestamps();

            $table->index('title');
            $table->index('iso');
            $table->index('country_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('states');
    }
};