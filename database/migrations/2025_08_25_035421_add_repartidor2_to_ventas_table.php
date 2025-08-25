<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Campo repartidor_2 nullable
            $table->foreignId('repartidor_2')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete(); // si se elimina el user => se pone null
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['repartidor_2']);
            $table->dropColumn('repartidor_2');
        });
    }
};
