<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'phone2_commercial')) {
                $table->dropColumn('phone2_commercial');
            }

            if (Schema::hasColumn('ventas', 'phone1_commercial')) {
                $table->dropColumn('phone1_commercial');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (!Schema::hasColumn('ventas', 'phone1_commercial')) {
                $table->string('phone1_commercial')->nullable()->after('comercial_id');
            }

            if (!Schema::hasColumn('ventas', 'phone2_commercial')) {
                $table->string('phone2_commercial')->nullable()->after('phone1_commercial');
            }
        });
    }
};