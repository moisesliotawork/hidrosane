<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('repartidor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete(); // o ->onDelete('set null')
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['repartidor_id']);
            $table->dropColumn('repartidor_id');
        });
    }

};
