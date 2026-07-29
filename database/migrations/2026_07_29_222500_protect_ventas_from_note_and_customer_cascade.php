<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['note_id']);
            $table->dropForeign(['customer_id']);
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedBigInteger('note_id')->nullable()->change();
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->foreign('note_id')
                ->references('id')->on('notes')
                ->nullOnDelete();

            $table->foreign('customer_id')
                ->references('id')->on('customers')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['note_id']);
            $table->dropForeign(['customer_id']);
        });

        // Restaurar NOT NULL solo si no hay note_id nulos
        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedBigInteger('note_id')->nullable(false)->change();
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->foreign('note_id')
                ->references('id')->on('notes')
                ->cascadeOnDelete();

            $table->foreign('customer_id')
                ->references('id')->on('customers')
                ->cascadeOnDelete();
        });
    }
};
