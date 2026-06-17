<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('punto_comercial_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_leader_id')->constrained('users')->cascadeOnDelete();
            $table->date('report_date');
            $table->text('texto');
            $table->string('lat')->nullable();
            $table->string('lng')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['report_date', 'submitted_at']);
            $table->index('team_leader_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('punto_comercial_reports');
    }
};
