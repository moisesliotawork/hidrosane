<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            
            $table->string('postal_code', 10)->nullable()->after('postal_code_id');
            $table->string('ciudad', 100)->nullable()->after('postal_code');
            $table->string('provincia', 100)->nullable()->after('ciudad');

          
            $table->index('postal_code');
            $table->index('ciudad');
            $table->index('provincia');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['postal_code']);
            $table->dropIndex(['ciudad']);
            $table->dropIndex(['provincia']);
            $table->dropColumn(['postal_code', 'ciudad','provincia']);
        });
    }
};
