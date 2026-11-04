<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ventas', 'alerta_borrado_vista_at')) {
            return;
        }

        Schema::table('ventas', function (Blueprint $table) {
            $table->timestamp('alerta_borrado_vista_at')->nullable()->after('reservado_by_user_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ventas', 'alerta_borrado_vista_at')) {
            return;
        }

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('alerta_borrado_vista_at');
        });
    }
};
