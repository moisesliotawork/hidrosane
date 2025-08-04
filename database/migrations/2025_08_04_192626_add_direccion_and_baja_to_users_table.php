<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ➕ Campos nuevos (ambos pueden quedar vacíos)
            $table->string('direccion')->nullable()->after('phone');
            $table->dateTime('baja')->nullable()->after('direccion');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['direccion', 'baja']);
        });
    }
};
