<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Oferta;

class NewOfertasSeeder extends Seeder
{
    public function run(): void
    {
        $ofertas = [

            /* =========================
             |  OFERTA SENCILLA
             |=========================*/
            [
                'nombre' => 'Oferta sencilla 4pts 1946,10€',
                'precio_base' => 1946.10,
                'puntos_base' => 4,
                'descripcion' => '49,90€ x 39 / 81,09€ x 24',
                'visible' => true,
            ],
            [
                'nombre' => 'Oferta sencilla 5pts 2199€',
                'precio_base' => 2199.00,
                'puntos_base' => 5,
                'descripcion' => '56,38€ x 39 / 91,62€ x 24',
                'visible' => true,
            ],
            [
                'nombre' => 'Oferta sencilla 7pts 2599€',
                'precio_base' => 2599.00,
                'puntos_base' => 7,
                'descripcion' => '66,64€ x 39 / 108,29€ x 24',
                'visible' => true,
            ],
            [
                'nombre' => 'Oferta sencilla 8pts 2899€',
                'precio_base' => 2899.00,
                'puntos_base' => 8,
                'descripcion' => '74,33€ x 39 / 120,79€ x 24',
                'visible' => true,
            ],

            /* =========================
             |  DOBLETE
             |=========================*/
            [
                'nombre' => 'Oferta doblete 8pts 3892,20€',
                'precio_base' => 3892.20,
                'puntos_base' => 8,
                'descripcion' => '99,80€ x 39 / 162,17€ x 24',
                'visible' => true,
            ],
            [
                'nombre' => 'Oferta doblete especial 6pts 3764€',
                'precio_base' => 3764.00,
                'puntos_base' => 6,
                'descripcion' => '96,51€ x 39 / 156,83€ x 24',
                'visible' => true,
            ],

            /* =========================
             |  TRIPLETE / CUÁDRUPLE
             |=========================*/
            [
                'nombre' => 'Oferta tripl.esp 9pts 5099€',
                'precio_base' => 5099.00,
                'puntos_base' => 9,
                'descripcion' => '130,74€ x 39 / 212,46€ x 24',
                'visible' => true,
            ],
            [
                'nombre' => 'Oferta cuadrup.esp 8pts 5999€',
                'precio_base' => 5999.00,
                'puntos_base' => 8,
                'descripcion' => '153,82€ x 39 / 249,99€ x 24',
                'visible' => true,
            ],
            [
                'nombre' => 'Oferta cuadrup.esp 10pts 6390€',
                'precio_base' => 6390.00,
                'puntos_base' => 10,
                'descripcion' => '163,85€ x 39 / 266,25€ x 24',
                'visible' => true,
            ],

            /* =========================
             |  MEDIA VENTA
             |=========================*/
            [
                'nombre' => 'Oferta media.vta 3pts 1656,10€',
                'precio_base' => 1656.10,
                'puntos_base' => 3,
                'descripcion' => '42,46€ pago único / 69€ x 24',
                'visible' => true,
            ],

            /* =========================
             |  REPARTO
             |=========================*/
            [
                'nombre' => 'Oferta reparto 3pts 1850€',
                'precio_base' => 1850.00,
                'puntos_base' => 1,
                'descripcion' => '39 cuotas de 47,44€ / 24 cuotas de 77,08€',
                'visible' => true,
            ],

            [
                'nombre' => 'Oferta 1946,10€ canapé/somier.art+colchon 90/105 6pts',
                'puntos_base' => 6,
                'precio_base' => 1946.10
            ],
            [
                'nombre' => 'Oferta 2199€ canapé/somier.art+colchon 6pts',
                'puntos_base' => 6,
                'precio_base' => 2199
            ],
            [
                'nombre' => 'Oferta 3764€ somier art.+colchon+topper 7pts',
                'puntos_base' => 7,
                'precio_base' => 3764
            ],
        ];

        foreach ($ofertas as $oferta) {
            Oferta::updateOrCreate(
                ['nombre' => $oferta['nombre']],
                $oferta
            );
        }
    }
}
