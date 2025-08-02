<?php

use App\Enums\EstadoEntrega;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('repartos', function (Blueprint $table) {
            // Nueva columna ENUM con valor por defecto «no_entregado»
            $table->string('estado_entrega')
                ->default(EstadoEntrega::NO_ENTREGADO->value)
                ->after('estado');          // colócala justo después de «estado»
        });
    }

    public function down(): void
    {
        Schema::table('repartos', function (Blueprint $table) {
            $table->dropColumn('estado_entrega');
        });
    }
};
