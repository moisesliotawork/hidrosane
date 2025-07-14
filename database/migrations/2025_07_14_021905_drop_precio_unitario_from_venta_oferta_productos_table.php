<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('venta_oferta_productos', function (Blueprint $table) {
            // Elimina la columna solo si existe
            if (Schema::hasColumn('venta_oferta_productos', 'precio_unitario')) {
                $table->dropColumn('precio_unitario');
            }
        });
    }

    public function down(): void
    {
        Schema::table('venta_oferta_productos', function (Blueprint $table) {
            // Vuelve a crearla por si haces rollback
            $table->decimal('precio_unitario', 10, 2)->after('cantidad');
        });
    }
};
