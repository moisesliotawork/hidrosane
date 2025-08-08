<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repartos', function (Blueprint $table) {
            $table->boolean('cliente_firma_garantias')->default(false)->after('estado_entrega');
            $table->boolean('cliente_comentario_goodwork')->default(false)->after('cliente_firma_garantias');
            $table->boolean('cliente_firma_digital')->default(false)->after('cliente_comentario_goodwork');
        });
    }

    public function down(): void
    {
        Schema::table('repartos', function (Blueprint $table) {
            $table->dropColumn([
                'cliente_firma_garantias',
                'cliente_comentario_goodwork',
                'cliente_firma_digital',
            ]);
        });
    }
};
