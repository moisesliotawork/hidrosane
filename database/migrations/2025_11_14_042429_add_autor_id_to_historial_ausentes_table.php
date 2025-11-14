<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('historial_ausentes', function (Blueprint $table) {
            $table->foreignId('autor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('note_id');
        });
    }

    public function down(): void
    {
        Schema::table('historial_ausentes', function (Blueprint $table) {
            $table->dropForeign(['autor_id']);
            $table->dropColumn('autor_id');
        });
    }
};
