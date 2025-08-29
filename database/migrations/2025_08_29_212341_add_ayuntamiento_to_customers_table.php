<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // tamaño libre; ajusta si quieres limitar (p.ej. ->string('ayuntamiento', 120))
            $table->string('ayuntamiento')->nullable()->after('parish');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('ayuntamiento');
        });
    }
};
