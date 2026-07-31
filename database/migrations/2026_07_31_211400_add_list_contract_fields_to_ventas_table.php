<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->boolean('en_app')->default(false)->after('nro_contr_adm');
            $table->text('list_descripcion')->nullable()->after('en_app');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['en_app', 'list_descripcion']);
        });
    }
};
