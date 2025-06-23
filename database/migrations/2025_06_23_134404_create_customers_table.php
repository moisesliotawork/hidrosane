<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_names', 255);
            $table->string('last_names', 255);
            $table->string('phone', 20);
            $table->string('secondary_phone', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('postal_code', 20);
            $table->string('primary_address', 255);
            $table->string('secondary_address', 255)->nullable();
            $table->string('parish', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('customers');
    }
};
