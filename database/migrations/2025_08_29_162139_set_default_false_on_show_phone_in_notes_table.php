<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Backfill: pon en 0 las notas que tengan show_phone NULL
        DB::table('notes')
            ->whereNull('show_phone')
            ->update(['show_phone' => 0]);

        // 2) Forzar default 0 y NOT NULL en la columna
        Schema::table('notes', function (Blueprint $table) {
            $table->boolean('show_phone')
                ->default(false)
                ->nullable(false)
                ->change();
        });
    }

    public function down(): void
    {
        // Revertir a NULL por compatibilidad (si antes era nullable sin default)
        Schema::table('notes', function (Blueprint $table) {
            $table->boolean('show_phone')
                ->nullable()
                ->default(null)
                ->change();
        });
    }
};
