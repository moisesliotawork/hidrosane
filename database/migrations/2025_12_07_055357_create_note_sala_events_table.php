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
        Schema::create('note_sala_events', function (Blueprint $table) {
            $table->id();

            // Nota a la que pertenece el evento
            $table->foreignId('note_id')
                ->constrained('notes')
                ->cascadeOnDelete();

            // Quién mandó la nota a sala
            $table->foreignId('sent_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Desde dónde se envió: 'declaracion', 'comando', 'masivo', etc.
            $table->string('via', 50);

            // Momento exacto del envío (además de created_at si quieres)
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('note_sala_events');
    }
};
