<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->boolean('es_puerta_fria')
                ->default(true)
                ->nullable()
                ->after('motivo_venta'); 
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('es_puerta_fria');
        });
    }
};
