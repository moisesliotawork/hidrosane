<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Si usas MySQL, puedes usar ENUM para forzar los valores permitidos a nivel BD.
            $table->enum('financiera', ['CREDIBOX', 'findirect', 'MONTJUIT'])
                  ->nullable()                 // permite NULL
                  ->after('modalidad_pago');   // colócala donde prefieras
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('financiera');
        });
    }
};
