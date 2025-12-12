<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('note_null_reasons', function (Blueprint $table) {
            $table->foreignId('companion_id')
                ->nullable()
                ->after('comercial_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('note_null_reasons', function (Blueprint $table) {
            $table->dropForeign(['companion_id']);
            $table->dropColumn('companion_id');
        });
    }
};
