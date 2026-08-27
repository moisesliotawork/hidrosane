<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $needsReservadoAt = ! Schema::hasColumn('ventas', 'reservado_at');
        $needsReservadoBy = ! Schema::hasColumn('ventas', 'reservado_by_user_id');

        Schema::table('ventas', function (Blueprint $table) use ($needsReservadoAt, $needsReservadoBy) {
            if ($needsReservadoAt) {
                $table->timestamp('reservado_at')->nullable();
            }
            if ($needsReservadoBy) {
                $table->foreignId('reservado_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        if (! Schema::hasColumn('ventas', 'deleted_at')) {
            return;
        }

        $update = [
            'reservado_at' => DB::raw('deleted_at'),
        ];

        if (Schema::hasColumn('ventas', 'deleted_by_user_id')) {
            $update['reservado_by_user_id'] = DB::raw('deleted_by_user_id');
        }

        DB::table('ventas')
            ->whereNotNull('deleted_at')
            ->whereNull('reservado_at')
            ->update($update);
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'reservado_by_user_id')) {
                $table->dropForeign(['reservado_by_user_id']);
            }
            $drop = array_values(array_filter([
                Schema::hasColumn('ventas', 'reservado_at') ? 'reservado_at' : null,
                Schema::hasColumn('ventas', 'reservado_by_user_id') ? 'reservado_by_user_id' : null,
            ]));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
