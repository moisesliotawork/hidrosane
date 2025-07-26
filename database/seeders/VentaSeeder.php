<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Note, Venta, VentaOferta, VentaOfertaProducto, Oferta, Producto};
use Illuminate\Support\Arr;

class VentaSeeder extends Seeder
{
    public function run(): void
    {
        $notas = Note::has('customer')->take(50)->get(); // Toma hasta 50 notas con cliente

        foreach ($notas as $nota) {
            $venta = Venta::create([
                'note_id' => $nota->id,
                'customer_id' => $nota->customer_id,
                'comercial_id' => $nota->comercial_id ?? 1,
                'fecha_venta' => now()->subDays(rand(0, 30)),
                'fecha_entrega' => now()->addDays(rand(1, 7))->toDateString(),
                'horario_entrega' => Arr::random(['Mañana', 'Tarde']),
                'importe_total' => rand(300, 1200),
                'modalidad_pago' => Arr::random(['Contado', 'Financiado']),
                'forma_pago' => Arr::random(['Efectivo', 'Tarjeta', 'Transferencia']),
                'num_cuotas' => rand(3, 12),
                'cuota_mensual' => rand(100, 250),
                'accesorio_entregado' => Arr::random(['Ninguno', 'Kit Hogar', 'Almohada']),
                'interes_art' => rand(0, 1),
                'status' => Arr::random(['BORRADOR', 'ENVIADA', 'VALIDADA', 'RECHAZADA']),
            ]);

            // Asociar una oferta aleatoria
            $oferta = Oferta::inRandomOrder()->first();

            if ($oferta) {
                $ventaOferta = VentaOferta::create([
                    'venta_id' => $venta->id,
                    'oferta_id' => $oferta->id,
                    'puntos' => rand(10, 50),
                ]);

                // Seleccionar productos aleatorios del catálogo general
                $productos = Producto::inRandomOrder()->take(2)->get();

                foreach ($productos as $producto) {
                    VentaOfertaProducto::create([
                        'venta_oferta_id' => $ventaOferta->id,
                        'producto_id' => $producto->id,
                        'cantidad' => rand(1, 3),
                        'puntos_linea' => rand(1, 5),
                    ]);
                }
            }
        }
    }
}
