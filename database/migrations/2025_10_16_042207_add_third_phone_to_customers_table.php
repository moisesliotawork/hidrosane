<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Para MySQL puedes usar ->after('secondary_phone'); si usas Postgres, quítalo.
            $column = $table->string('third_phone', 30)->nullable()->default(null);
            if (method_exists($column, 'after')) {
                $column->after('secondary_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('third_phone');
        });
    }
};
