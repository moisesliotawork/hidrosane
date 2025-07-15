<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Producto;

class ProductosSimplesSeeder extends Seeder
{
    public function run(): void
    {
        $productos = [
            ['nombre' => 'Purificador Aire Fresh Life V10', 'puntos' => 1],
            ['nombre' => 'Batería Sartenes Toscana y 12 PZ Acero', 'puntos' => 1],
            ['nombre' => 'Obra La Santa Biblia Ilustrada', 'puntos' => 1],
            ['nombre' => 'Acc. Brazal Preso', 'puntos' => 1],
            ['nombre' => 'Cambio Filtros Depurador Osm. Inversa', 'puntos' => 1],
            ['nombre' => 'Grifo 3 Vías', 'puntos' => 1],
            ['nombre' => 'Robor Cocina Master Cook', 'puntos' => 1],
            ['nombre' => 'Microondas', 'puntos' => 1],
            ['nombre' => 'Reloj', 'puntos' => 1],
            ['nombre' => 'Generador Ozono Smart V10', 'puntos' => 1],
            ['nombre' => 'Somier Fijo Med. Especiales', 'puntos' => 1],
            ['nombre' => 'Masajeador de Manos', 'puntos' => 1],
            ['nombre' => 'Pack Almohadas', 'puntos' => 1],
            ['nombre' => 'Ejercitador Pies 30 Velocidades', 'puntos' => 1],
            ['nombre' => 'Purificador Ozono Abantera 800', 'puntos' => 1],
            ['nombre' => 'Cavita Slim V10', 'puntos' => 1],
            ['nombre' => 'Edredón Zenany', 'puntos' => 1],
            ['nombre' => 'Láser Cuello y Cervicales', 'puntos' => 1],
            ['nombre' => 'Physio Mass Rodilla V10', 'puntos' => 1],
            ['nombre' => 'Physio Mass Lumbar V10', 'puntos' => 1],
            ['nombre' => 'Desk Bike', 'puntos' => 1],
            ['nombre' => 'Bicicleta Estática AT456', 'puntos' => 1],
            ['nombre' => 'Freidora Duo', 'puntos' => 1],
            ['nombre' => 'Set Maletas Trolley', 'puntos' => 1],
            ['nombre' => 'Móvil Smartphone', 'puntos' => 1],
            ['nombre' => 'Jarra de Agua Hidrogenada', 'puntos' => 1],
            ['nombre' => 'Barandillas para Somier Art.', 'puntos' => 2],
            ['nombre' => 'Descalcificador Deecal Hidrosalud', 'puntos' => 2],
            ['nombre' => 'Tablet Lenovo', 'puntos' => 2],
            ['nombre' => 'Magnetofield + Accesorios', 'puntos' => 2],
            ['nombre' => 'Chimenea Erika Curve', 'puntos' => 2],
            ['nombre' => 'Robot Aspirador', 'puntos' => 2],
            ['nombre' => 'Deshumidificador Taurus (1,5 puntos)', 'puntos' => 2],
            ['nombre' => 'Freidora Digital Abantera', 'puntos' => 2],
            ['nombre' => 'Batería 25 PZS V10 o T-Kuro', 'puntos' => 2],
            ['nombre' => 'Presoterapia Viaje Abantera', 'puntos' => 2],
            ['nombre' => 'Bicicleta Elíptica Grindilux V10', 'puntos' => 2],
            ['nombre' => 'Centro Planchado Abantera', 'puntos' => 2],
            ['nombre' => 'Láser Espalda y Abdomen Ondulación', 'puntos' => 2],
            ['nombre' => 'Master Mix Plus', 'puntos' => 2],
            ['nombre' => 'Cinta de Correr AT 118', 'puntos' => 2],
            ['nombre' => 'Aire Acondicionado Frio/Calor', 'puntos' => 2],
            ['nombre' => 'Preso Ondulación Cabeza Abantera', 'puntos' => 2],
            ['nombre' => 'Canapé Abatible', 'puntos' => 3],
            ['nombre' => 'Sillón Levanta Personas', 'puntos' => 3],
            ['nombre' => 'Magneto Doble', 'puntos' => 3],
            ['nombre' => 'Sofá 2PL 145x100x95', 'puntos' => 3],
            ['nombre' => 'Smart TV 32"24"', 'puntos' => 3],
            ['nombre' => 'Generador Ozono Lavadora UltraWash', 'puntos' => 3],
            ['nombre' => 'Depurador Flujo Directo', 'puntos' => 3],
            ['nombre' => 'Pendientes', 'puntos' => 3],
            ['nombre' => 'Anillos', 'puntos' => 3],
            ['nombre' => 'Master Mix E-Touch', 'puntos' => 3],
            ['nombre' => 'Robot Cocina New King Abantera', 'puntos' => 3],
            ['nombre' => 'Canapé Abatible de Madera 200x200', 'puntos' => 4],
            ['nombre' => 'Lavavajillas', 'puntos' => 4],
            ['nombre' => 'Lavadora', 'puntos' => 4],
            ['nombre' => 'Frigorífico Combi', 'puntos' => 4],
            ['nombre' => 'Smart TV 43"50"', 'puntos' => 4],
            ['nombre' => 'Presoterapia Abantera', 'puntos' => 4],
            ['nombre' => 'Smartphone Alta Gama', 'puntos' => 4],
            ['nombre' => 'Ordenador Portátil 15"', 'puntos' => 4],
            ['nombre' => 'Cinta de Correr Abantera', 'puntos' => 5],
            ['nombre' => 'Smart TV 55"65" 4K', 'puntos' => 5],
            ['nombre' => 'Lavadora o Secadora Inox', 'puntos' => 5],
            ['nombre' => 'Sofá 3 PL 195x105x100 Talisman Beige', 'puntos' => 5],
            ['nombre' => 'Chaiselongue Talisman 285x105x155', 'puntos' => 6],
            ['nombre' => 'Cama Asistencial Elevadora + Barras Protect.', 'puntos' => 8],
            ['nombre' => 'Producto Externo', 'puntos' => 15],
        ];

        foreach ($productos as $producto) {
            Producto::create($producto);
        }
    }
}
