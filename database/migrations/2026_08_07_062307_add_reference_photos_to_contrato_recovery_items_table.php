<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contrato_recovery_items', function (Blueprint $table) {
            $table->json('reference_photos')->nullable()->after('documents');
        });
    }

    public function down(): void
    {
        Schema::table('contrato_recovery_items', function (Blueprint $table) {
            $table->dropColumn('reference_photos');
        });
    }
};
