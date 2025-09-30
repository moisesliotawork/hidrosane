<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            // Fecha y hora en que la nota se envió a sala
            // nullable y por defecto NULL
            $table->timestamp('sent_to_sala_at')->nullable()->default(null)
                ->after('estado_terminal'); // ajusta la ubicación si prefieres
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn('sent_to_sala_at');
        });
    }
};

