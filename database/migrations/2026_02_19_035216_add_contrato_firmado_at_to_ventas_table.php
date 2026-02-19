<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('ventas', 'contrato_firmado_at')) {
            Schema::table('ventas', function (Blueprint $table) {
                $table->timestamp('contrato_firmado_at')->nullable()->after('contrato_firmado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ventas', 'contrato_firmado_at')) {
            Schema::table('ventas', function (Blueprint $table) {
                $table->dropColumn('contrato_firmado_at');
            });
        }
    }
};
