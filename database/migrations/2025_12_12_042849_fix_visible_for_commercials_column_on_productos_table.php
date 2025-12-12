<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('productos', 'visible_for_commercials')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->boolean('visible_for_commercials')
                    ->default(true)
                    ->after('delete');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('productos', 'visible_for_commercials')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->dropColumn('visible_for_commercials');
            });
        }
    }
};
