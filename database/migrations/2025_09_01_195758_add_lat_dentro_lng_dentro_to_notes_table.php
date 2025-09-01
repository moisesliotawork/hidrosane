<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->string('lat_dentro', 20)->nullable()->after('lng');
            $table->string('lng_dentro', 20)->nullable()->after('lat_dentro');
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn(['lat_dentro', 'lng_dentro']);
        });
    }
};
