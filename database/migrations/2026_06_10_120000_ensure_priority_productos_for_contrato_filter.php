<?php

use App\Models\Producto;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $productos = [
            ['nombre' => 'Depurador Vita Abantera', 'puntos' => 3],
            ['nombre' => 'Depurador de mesa Abantera', 'puntos' => 3],
        ];

        foreach ($productos as $producto) {
            Producto::query()->firstOrCreate(
                ['nombre' => $producto['nombre']],
                [
                    'puntos' => $producto['puntos'],
                    'delete' => false,
                ]
            );
        }
    }

    public function down(): void
    {
        // No se eliminan: pueden estar referenciados en contratos.
    }
};
