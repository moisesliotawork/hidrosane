<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cream_daily_controls', function (Blueprint $table) {
            $table->unsignedInteger('received')->default(0);
            $table->unsignedInteger('donated')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('cream_daily_controls', function (Blueprint $table) {
            $table->dropColumn(['received', 'donated']);
        });
    }
};
