<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoMedida;

class TiposMedidaSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['nombre' => 'Almohada', 'unidad' => 'cm'],
            ['nombre' => 'Colchón / Somier', 'unidad' => 'cm'],
            ['nombre' => 'Topper', 'unidad' => 'cm'],
        ];

        foreach ($tipos as $tipo) {
            TipoMedida::Create($tipo);
        }
    }
}
