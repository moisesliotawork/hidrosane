<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // -- Documentos que se subirán como archivos (ruta/filename)
            $table->string('precontractual')->nullable()->after('productos_externos');
            $table->string('dni_anverso')->nullable();
            $table->string('dni_reverso')->nullable();
            $table->string('documento_titularidad')->nullable();
            $table->string('nomina')->nullable();
            $table->string('pension')->nullable();
            $table->string('contrato_firmado')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn([
                'precontractual',
                'dni_anverso',
                'dni_reverso',
                'documento_titularidad',
                'nomina',
                'pension',
                'contrato_firmado',
            ]);
        });
    }
};
