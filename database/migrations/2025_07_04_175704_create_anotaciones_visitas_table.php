<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('anotaciones_visitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_id')->constrained('notes')->onDelete('cascade');
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->string('asunto');
            $table->text('cuerpo');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('anotaciones_visitas');
    }
};