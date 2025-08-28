<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // MySQL / MariaDB
        DB::statement("
            UPDATE customers c
            LEFT JOIN ventas v ON v.customer_id = c.id
            SET c.nro_cliente = NULL
            WHERE v.id IS NULL
        ");
    }

    public function down(): void
    {
        // (opcional) volver a calcular por id+525, o dejar vacío
        // DB::statement("UPDATE customers SET nro_cliente = LPAD(id + 525, 5, '0') WHERE nro_cliente IS NULL");
    }
};
