<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_phone_logs', function (Blueprint $table) {
            $table->unsignedTinyInteger('phone_slot')->nullable()->after('customer_id'); // 1 = COM1, 2 = COM2
            $table->string('phone_value', 30)->nullable()->after('phone_slot');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_phone_logs', function (Blueprint $table) {
            $table->dropColumn(['phone_slot', 'phone_value']);
        });
    }
};
