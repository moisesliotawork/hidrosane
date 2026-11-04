<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financieras', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 100)->unique();
            $table->string('nombre', 100)->unique();
            $table->date('fecha_inclusion');
            $table->timestamp('baja_at')->nullable();
            $table->timestamps();
        });

        $ahora = now();

        foreach (\App\Models\Financiera::registrosIniciales() as $fila) {
            DB::table('financieras')->updateOrInsert(
                ['codigo' => $fila['codigo']],
                [
                    'nombre' => $fila['nombre'],
                    'fecha_inclusion' => $fila['fecha_inclusion'],
                    'baja_at' => null,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ],
            );
        }

        if (Schema::hasColumn('ventas', 'financiera') && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ventas MODIFY COLUMN financiera VARCHAR(100) NULL DEFAULT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financieras');
    }
};
