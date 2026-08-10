<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_pdf_downloads', function (Blueprint $table) {
            $table->string('origen', 20)->default('descarga')->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('venta_pdf_downloads', function (Blueprint $table) {
            $table->dropColumn('origen');
        });
    }
};
