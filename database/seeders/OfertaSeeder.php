<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Oferta;
use Illuminate\Support\Str;

class OfertaSeeder extends Seeder
{
    public function run(): void
    {
        /** Catálogo completo de ofertas – puntos y precio en € */
        $ofertas = [
            // ───────────── OFERTAS SENCILLAS ─────────────
            ['nombre' => 'Oferta sencilla 4pts 1899€', 'puntos' => 4, 'precio' => 1899],
            ['nombre' => 'Oferta sencilla 5pts 2099€', 'puntos' => 5, 'precio' => 2099],
            ['nombre' => 'Oferta sencilla 7pts 2499€', 'puntos' => 7, 'precio' => 2499],
            ['nombre' => 'Oferta sencilla 8pts 2799€', 'puntos' => 8, 'precio' => 2799],

            // ─────────────────── DOBLETE ─────────────────
            ['nombre' => 'Oferta doblete 6pts 3564€', 'puntos' => 6, 'precio' => 3564],
            ['nombre' => 'Oferta doblete 8pts 3798€', 'puntos' => 8, 'precio' => 3798],

            // ─────────────── TRIPLETE ESPECIAL ───────────
            ['nombre' => 'Oferta tripl.esp 9pts 4799€', 'puntos' => 9, 'precio' => 4799],

            // ────────────── CUÁDRUPLE ESPECIAL ───────────
            ['nombre' => 'Oferta cuadrup.esp 9pts 5990€', 'puntos' => 9, 'precio' => 5990],

            // ───────────────── MEDIA VENTA ───────────────
            ['nombre' => 'Oferta media.vta 3pts 1556,10', 'puntos' => 3, 'precio' => 1556.10],

            // ──────────────── OFERTA REPARTO ─────────────
            ['nombre' => 'Oferta reparto 3pts 1750€', 'puntos' => 3, 'precio' => 1750],

            // ───────────────── EXCEPCIONES ───────────────
            ['nombre' => 'Oferta 1899€ canapé/somier.art+colchon 90/105 6pts', 'puntos' => 6, 'precio' => 1899],
            ['nombre' => 'Oferta 2099€ canapé/somier.art+colchon 6pts', 'puntos' => 6, 'precio' => 2099],
            ['nombre' => 'Oferta 3564€ somier art.+colchon+topper 7pts', 'puntos' => 7, 'precio' => 3564],
        ];

        foreach ($ofertas as $item) {
            Oferta::updateOrCreate(
                [
                    'nombre' => $item['nombre'],
                    'puntos_base' => $item['puntos'],
                    'precio_base' => $item['precio'],
                    'descripcion' => $item['nombre'],
                ]
            );
        }
    }
}
