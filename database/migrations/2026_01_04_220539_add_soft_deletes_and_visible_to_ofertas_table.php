<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ofertas', function (Blueprint $table) {
            // Campo para ocultar/mostrar en la app
            $table->boolean('visible')->default(true)->after('descripcion');

            // Soft delete
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('ofertas', function (Blueprint $table) {
            $table->dropColumn('visible');
            $table->dropSoftDeletes();
        });
    }
};
