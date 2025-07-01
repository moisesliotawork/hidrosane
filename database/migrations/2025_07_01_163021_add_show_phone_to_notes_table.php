<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->boolean('show_phone')->default(true)->after('lng');
        });
    }

    public function down()
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn('show_phone');
        });
    }
};