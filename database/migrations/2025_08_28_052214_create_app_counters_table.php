<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_counters', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();
        });

        // Tomamos el máximo actual (como entero) para continuar la secuencia
        $max = DB::table('customers')
            ->whereNotNull('nro_cliente')
            ->max(DB::raw('CAST(nro_cliente AS UNSIGNED)'));

        $seed = max(525, (int) $max); // 525 si no hay ninguno, o el máximo actual si ya hay

        DB::table('app_counters')->insert([
            'name' => 'nro_cliente',
            'value' => $seed,   // "último asignado"; el próximo será value+1
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_counters');
    }
};
