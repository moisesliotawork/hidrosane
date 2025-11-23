<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cream_daily_controls', function (Blueprint $table) {
            $table->unsignedInteger('next_day_to_assign')
                ->default(0)
                ->after('remaining');
        });
    }

    public function down(): void
    {
        Schema::table('cream_daily_controls', function (Blueprint $table) {
            $table->dropColumn('next_day_to_assign');
        });
    }
};
