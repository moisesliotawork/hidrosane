<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // EXACTO 5 caracteres. Si en el futuro pudieras superar 99,474 clientes,
            // considera ampliar a 6+ para no truncar.
            $table->string('nro_cliente', 5)->nullable()->unique()->after('id');
        });

        // Backfill para los existentes: nro_cliente = LPAD(id + 525, 5, '0')
        DB::statement("
            UPDATE `customers`
            SET `nro_cliente` = LPAD(`id` + 525, 5, '0')
            WHERE `nro_cliente` IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['nro_cliente']);
            $table->dropColumn('nro_cliente');
        });
    }
};
