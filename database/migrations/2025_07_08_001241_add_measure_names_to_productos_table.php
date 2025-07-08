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
        Schema::table('productos', function (Blueprint $table) {
            $table->string('nombre_medida1')->nullable()->after('medida1');
            $table->string('nombre_medida2')->nullable()->after('medida2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['nombre_medida1', 'nombre_medida2']);
        });
    }
};