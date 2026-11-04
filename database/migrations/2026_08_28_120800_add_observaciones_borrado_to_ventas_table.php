<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('ventas', 'observaciones_borrado')) {
                $table->text('observaciones_borrado')
                    ->nullable()
                    ->after('deleted_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'observaciones_borrado')) {
                $table->dropColumn('observaciones_borrado');
            }
        });
    }
};
