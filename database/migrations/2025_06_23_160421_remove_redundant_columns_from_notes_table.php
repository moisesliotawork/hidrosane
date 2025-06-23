<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('notes', function (Blueprint $table) {
            // Eliminar columnas que ahora están en customers
            $table->dropColumn([
                'first_names',
                'last_names',
                'phone',
                'secondary_phone',
                'email',
                'postal_code',
                'primary_address',
                'secondary_address',
                'parish'
            ]);
        });
    }

    public function down()
    {
        Schema::table('notes', function (Blueprint $table) {
            // Recrear las columnas eliminadas (para rollback)
            $table->string('first_names', 255)->after('customer_id');
            $table->string('last_names', 255)->after('first_names');
            $table->string('phone', 20)->after('last_names');
            $table->string('secondary_phone', 20)->nullable()->after('phone');
            $table->string('email', 255)->after('secondary_phone');
            $table->string('postal_code', 20)->after('email');
            $table->string('primary_address', 255)->after('postal_code');
            $table->string('secondary_address', 255)->nullable()->after('primary_address');
            $table->string('parish', 255)->nullable()->after('secondary_address');

            // Volver a hacer customer_id nullable si era necesario
            $table->foreignId('customer_id')->nullable()->change();
        });
    }
};