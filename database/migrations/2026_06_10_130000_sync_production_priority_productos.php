<?php

use App\Models\Producto;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Nombres exactos en producción (Admin → Productos). */
    private function productionProducts(): array
    {
        return [
            [
                'nombre' => 'DEPURADOR VITA ABANTERA',
                'puntos' => 1,
                'aliases' => [
                    'Depurador Vita Abantera',
                    'depurador vita abantera',
                ],
            ],
            [
                'nombre' => 'DEPURADORA DE MESA ABANTERA',
                'puntos' => 3,
                'aliases' => [
                    'Depurador de mesa Abantera',
                    'depurador de mesa abantera',
                    'depuradora de mesa abantera',
                ],
            ],
            [
                'nombre' => 'FILTROS DEPURADORA OSMOSIS',
                'puntos' => 1,
                'aliases' => [
                    'Filtros Depuradora Osmosis',
                    'filtros depuradora osmosis',
                    'Cambio Filtros Depurador Osm. Inversa',
                ],
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->productionProducts() as $spec) {
            $existing = Producto::query()
                ->where('nombre', $spec['nombre'])
                ->first();

            if (!$existing) {
                foreach ($spec['aliases'] as $alias) {
                    $existing = Producto::query()
                        ->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower(trim($alias))])
                        ->first();

                    if ($existing) {
                        break;
                    }
                }
            }

            if ($existing) {
                $existing->update([
                    'nombre' => $spec['nombre'],
                    'puntos' => $spec['puntos'],
                    'delete' => false,
                    'visible_for_commercials' => true,
                ]);
                continue;
            }

            Producto::query()->create([
                'nombre' => $spec['nombre'],
                'puntos' => $spec['puntos'],
                'delete' => false,
                'visible_for_commercials' => true,
            ]);
        }
    }

    public function down(): void
    {
        // No revertir: pueden estar referenciados en contratos.
    }
};
