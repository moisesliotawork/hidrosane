<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // 12,2 para montos cómodos (ajusta si deseas más precisión)
            $table->decimal('monto_extra', 12, 2)->default(0)->after('importe_total');
            $table->decimal('total_final', 12, 2)->default(0)->after('monto_extra');
            $table->decimal('cuota_final', 12, 2)->default(0)->after('total_final');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['monto_extra', 'total_final', 'cuota_final']);
        });
    }
};
