<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            // user_id + end_time + updated_at para filtrar y ordenar
            $table->index(['user_id', 'end_time', 'updated_at'], 'ws_user_endtime_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropIndex('ws_user_endtime_updated_idx');
        });
    }
};

