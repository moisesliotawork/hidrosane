<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lista_amano', function (Blueprint $table) {
            $table->id();
            $table->string('mes_codigo', 32)->index(); // Mayo25, Sept25…
            $table->unsignedTinyInteger('mes')->index(); // 1–12
            $table->unsignedSmallInteger('anio')->index(); // 2025, 2026…
            $table->unsignedInteger('pagina')->nullable();
            $table->unsignedInteger('nro')->nullable()->index();
            $table->string('cliente')->index();
            $table->string('comercial_1')->nullable();
            $table->string('comercial_2')->nullable();
            $table->text('detalle')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['anio', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_amano');
    }
};
