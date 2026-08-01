<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos_mes_variacion_items', function (Blueprint $table) {
            $table->timestamp('ocurrido_at')->nullable()->after('dni');
            $table->foreignId('caused_by_user_id')->nullable()->after('ocurrido_at')
                ->constrained('users')->nullOnDelete();
            $table->string('caused_by_label')->nullable()->after('caused_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('contratos_mes_variacion_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('caused_by_user_id');
            $table->dropColumn(['ocurrido_at', 'caused_by_label']);
        });
    }
};
