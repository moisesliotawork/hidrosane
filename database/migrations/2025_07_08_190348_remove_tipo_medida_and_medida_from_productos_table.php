<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['tipo_medida', 'medida']);
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('tipo_medida')->nullable();
            $table->string('medida')->nullable();
        });
    }
};
