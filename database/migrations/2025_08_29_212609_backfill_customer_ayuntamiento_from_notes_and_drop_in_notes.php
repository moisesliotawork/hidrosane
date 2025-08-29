<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Copiar el ayuntamiento desde la NOTA más reciente (con valor no vacío) hacia el CUSTOMER
        //    Solo llena customers.ayuntamiento si está null o vacío.
        //    (MySQL/MariaDB)
        DB::statement(<<<SQL
            UPDATE customers c
            JOIN (
                SELECT n1.customer_id, n1.ayuntamiento
                FROM notes n1
                JOIN (
                    SELECT customer_id, MAX(created_at) AS max_created
                    FROM notes
                    WHERE ayuntamiento IS NOT NULL AND ayuntamiento <> ''
                    GROUP BY customer_id
                ) latest ON latest.customer_id = n1.customer_id
                       AND latest.max_created = n1.created_at
                WHERE n1.ayuntamiento IS NOT NULL AND n1.ayuntamiento <> ''
            ) x ON x.customer_id = c.id
            SET c.ayuntamiento = CASE
                WHEN (c.ayuntamiento IS NULL OR c.ayuntamiento = '')
                THEN x.ayuntamiento
                ELSE c.ayuntamiento
            END
        SQL);

        // 2) Eliminar la columna 'ayuntamiento' de notes
        if (Schema::hasColumn('notes', 'ayuntamiento')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->dropColumn('ayuntamiento');
            });
        }
    }

    public function down(): void
    {
        // Volver a crear la columna en notes (no re-movemos datos hacia atrás)
        Schema::table('notes', function (Blueprint $table) {
            $table->string('ayuntamiento')->nullable()->after('productos_externos');
        });
    }
};
