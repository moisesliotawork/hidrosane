<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('repartos', function (Blueprint $table) {
            $table->string('dentro_lat')->nullable()->after('lng');
            $table->string('dentro_lng')->nullable()->after('dentro_lat');
        });
    }

    public function down(): void
    {
        Schema::table('repartos', function (Blueprint $table) {
            $table->dropColumn(['dentro_lat', 'dentro_lng']);
        });
    }
};
