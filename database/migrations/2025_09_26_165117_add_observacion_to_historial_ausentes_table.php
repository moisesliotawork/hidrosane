<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('historial_ausentes', function (Blueprint $table) {
            $table->string('observacion')->nullable()->after('longitud');
        });
    }

    public function down(): void
    {
        Schema::table('historial_ausentes', function (Blueprint $table) {
            $table->dropColumn('observacion');
        });
    }
};
