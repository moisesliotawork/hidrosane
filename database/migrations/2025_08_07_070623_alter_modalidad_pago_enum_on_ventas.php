<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Agrega “NS” al enum modalidad_pago.
     */
    public function up(): void
    {
        // ⚠️  Usamos SQL crudo porque Doctrine no soporta ALTER ENUM
        DB::statement("
            ALTER TABLE ventas
            MODIFY modalidad_pago ENUM('Financiado','Contado','NS')
            NOT NULL DEFAULT 'Financiado'
        ");
    }

    /**
     * Revierte la columna al enum original.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE ventas
            MODIFY modalidad_pago ENUM('Financiado','Contado')
            NOT NULL DEFAULT 'Financiado'
        ");
    }
};
