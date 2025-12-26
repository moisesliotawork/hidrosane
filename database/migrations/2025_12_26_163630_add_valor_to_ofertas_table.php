<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ofertas', function (Blueprint $table) {
            $table->decimal('valor', 6, 2)
                ->default(1)
                ->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('ofertas', function (Blueprint $table) {
            $table->dropColumn('valor');
        });
    }
};
