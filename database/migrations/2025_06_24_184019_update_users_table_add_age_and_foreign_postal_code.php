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
        Schema::table('customers', function (Blueprint $table) {
            // 1. Eliminar la columna postal_code existente (si existe)
            if (Schema::hasColumn('customers', 'postal_code')) {
                $table->dropColumn('postal_code');
            }

            // 2. Agregar columna age
            $table->integer('age')->nullable()->after('email');

            // 3. Agregar columna postal_code_id como clave foránea
            $table->foreignId('postal_code_id')
                ->nullable()
                ->after('age')
                ->constrained('postal_codes')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // 1. Eliminar la relación foránea
            $table->dropForeign(['postal_code_id']);

            // 2. Eliminar la columna postal_code_id
            $table->dropColumn('postal_code_id');

            // 3. Eliminar la columna age
            $table->dropColumn('age');
        });
    }
};