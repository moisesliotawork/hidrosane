<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('ventas', 'document_slot_reviews')) {
                $table->json('document_slot_reviews')->nullable()->after('document_slot_marks');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'document_slot_reviews')) {
                $table->dropColumn('document_slot_reviews');
            }
        });
    }
};
