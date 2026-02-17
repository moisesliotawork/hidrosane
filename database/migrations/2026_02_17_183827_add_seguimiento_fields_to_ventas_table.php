<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Si la tabla no existe, no hacemos nada (extra safety)
        if (!Schema::hasTable('ventas')) {
            return;
        }

        Schema::table('ventas', function (Blueprint $table) {
            // Nota: Schema::hasColumn requiere el nombre real de la columna en DB
            if (!Schema::hasColumn('ventas', 'seguimiento')) {
                $table->string('seguimiento')->nullable()->after('updated_at');
            }

            if (!Schema::hasColumn('ventas', 'financieras_reparto')) {
                $table->string('financieras_reparto')->nullable()->after('seguimiento');
            }

            if (!Schema::hasColumn('ventas', 'pasadas_financieras')) {
                $table->string('pasadas_financieras')->nullable()->after('financieras_reparto');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ventas')) {
            return;
        }

        Schema::table('ventas', function (Blueprint $table) {
            // En rollback también: solo dropea si existen
            if (Schema::hasColumn('ventas', 'pasadas_financieras')) {
                $table->dropColumn('pasadas_financieras');
            }

            if (Schema::hasColumn('ventas', 'financieras_reparto')) {
                $table->dropColumn('financieras_reparto');
            }

            if (Schema::hasColumn('ventas', 'seguimiento')) {
                $table->dropColumn('seguimiento');
            }
        });
    }
};
