<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Producto;
use App\Models\TipoMedida;
use App\Models\ProductoMedida;

class ProductosConMedidasSeeder extends Seeder
{
    public function run(): void
    {
        // Medidas de almohadas
        $medidasAlmohada = ['60', '70', '75', '80', '90', '105', '120', '135', '150'];
        $tipoAlmohada = TipoMedida::where('nombre', 'Almohada')->first();

        foreach ($medidasAlmohada as $medida) {
            $producto = Producto::create(['nombre' => 'Almohada ' . $medida, 'puntos' => 1]);

            ProductoMedida::create([
                'producto_id' => $producto->id,
                'tipo_medida_id' => $tipoAlmohada->id,
                'valor' => $medida,
            ]);
        }

        // Medidas de colchón/somier
        $medidasColchon = [
            '67.5 x 180', '67.5 x 190', '67.5 x 200',
            '75 x 180', '75 x 190', '75 x 200',
            '80 x 180', '80 x 190', '80 x 200',
            '90 x 180', '90 x 190', '90 x 200',
            '98 x 83',
            '105 x 180', '105 x 190', '105 x 200',
            '110 x 180', '110 x 190', '110 x 200',
            '120 x 180', '120 x 190', '120 x 200',
            '130 x 180', '130 x 190', '130 x 200',
            '133 x 180', '133 x 190', '133 x 200',
            '135 x 180', '135 x 190', '135 x 200',
            '140 x 180', '140 x 190', '140 x 200',
            '145 x 194',
            '150 x 180', '150 x 190', '150 x 200',
            '160 x 180', '160 x 190', '160 x 200',
            '180 x 180', '180 x 190', '180 x 200',
            '200 x 180', '200 x 190', '200 x 200',
        ];
        $tipoColchon = TipoMedida::where('nombre', 'Colchón / Somier')->first();

        foreach ($medidasColchon as $medida) {
            $producto = Producto::create(['nombre' => 'Colchón/Somier ' . $medida, 'puntos' => 3]);

            ProductoMedida::create([
                'producto_id' => $producto->id,
                'tipo_medida_id' => $tipoColchon->id,
                'valor' => $medida,
            ]);
        }

        // Medidas de topper
        $medidasTopper = ['67.5', '75', '80', '90', '98', '105', '110', '120', '125', '130', '133', '135', '140', '145', '150', '160', '180'];
        $tipoTopper = TipoMedida::where('nombre', 'Topper')->first();

        foreach ($medidasTopper as $medida) {
            $producto = Producto::create(['nombre' => 'Topper ' . $medida, 'puntos' => 1]);

            ProductoMedida::create([
                'producto_id' => $producto->id,
                'tipo_medida_id' => $tipoTopper->id,
                'valor' => $medida,
            ]);
        }
    }
}
