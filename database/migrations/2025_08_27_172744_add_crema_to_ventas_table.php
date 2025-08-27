<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Ubica la columna donde te quede más lógico; aquí, por ejemplo, tras 'accesorio_entregado'
            $table->boolean('crema')->default(false)->after('accesorio_entregado');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('crema');
        });
    }
};

