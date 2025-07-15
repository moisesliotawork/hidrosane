<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFechaEntregaAndHorarioEntregaToVentasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Agrega la fecha de entrega como datetime
            $table->dateTime('fecha_entrega')
                  ->nullable()
                  ->after('fecha_venta');

            // Agrega el horario de entrega como string
            $table->string('horario_entrega')
                  ->nullable()
                  ->after('fecha_entrega');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['fecha_entrega', 'horario_entrega']);
        });
    }
}
