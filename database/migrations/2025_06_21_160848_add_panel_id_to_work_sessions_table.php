<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->string('panel_id')->nullable()->after('user_id');
        });
    }

    public function down()
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropColumn('panel_id');
        });
    }
};
