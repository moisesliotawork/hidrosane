<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            
            $table->string('dni', 20)->after('email');

           
            $table->date('fecha_nac')->nullable()->after('dni');
            $table->string('iban', 34)->nullable()->after('fecha_nac');

           
            $table->string('tipo_vivienda', 255)->nullable()->after('iban');        
            $table->string('estado_civil', length: 255)->nullable()->after('tipo_vivienda'); 
            $table->string('situacion_laboral', 255)->nullable()->after('estado_civil');
            $table->string('ingresos_rango', 255)->nullable()->after('situacion_laboral');

            $table->unsignedTinyInteger('num_hab_casa')->nullable()->after('ingresos_rango');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'dni',
                'fecha_nac',
                'iban',
                'tipo_vivienda',
                'estado_civil',
                'situacion_laboral',
                'ingresos_rango',
                'num_hab_casa',
            ]);
        });
    }
};
