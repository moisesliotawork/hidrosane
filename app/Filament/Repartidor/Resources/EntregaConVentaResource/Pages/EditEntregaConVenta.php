<?php

namespace App\Filament\Repartidor\Resources\EntregaConVentaResource\Pages;

use App\Filament\Repartidor\Resources\EntregaConVentaResource;
use Filament\Resources\Pages\EditRecord;
use App\Models\{VentaOferta, VentaOfertaProducto, Oferta};
use App\Enums\VendidoPor;

class EditEntregaConVenta extends EditRecord
{
    protected static string $resource = EntregaConVentaResource::class;

    protected function afterSave(): void
    {
        /** @var \App\Models\Venta $venta */
        $venta = $this->getRecord();

        $state = $this->form->getState();
        $nuevas = $state['nuevas_ofertas'] ?? [];

        if (!empty($nuevas)) {
            // 1) Crear packs + líneas
            foreach ($nuevas as $pack) {
                if (empty($pack['oferta_id'])) {
                    continue;
                }

                $vo = VentaOferta::create([
                    'venta_id' => $venta->id,
                    'oferta_id' => $pack['oferta_id'],
                    'puntos' => (int) ($pack['puntos'] ?? 0),
                ]);

                foreach (($pack['productos'] ?? []) as $linea) {
                    if (empty($linea['producto_id']) || empty($linea['cantidad'])) {
                        continue;
                    }

                    VentaOfertaProducto::create([
                        'venta_oferta_id' => $vo->id,
                        'producto_id' => $linea['producto_id'],
                        'cantidad' => (int) $linea['cantidad'],
                        'cantidad_entregada' => 0,
                        'puntos_linea' => (int) ($linea['puntos_linea'] ?? 0),
                        'vendido_por' => VendidoPor::Repartidor, // 👈 forzado
                    ]);
                }
            }

            // 2) Sumar al importe_total el precio_base de las nuevas ofertas
            $ids = collect($nuevas)->pluck('oferta_id')->filter()->all();
            $extra = (float) Oferta::whereIn('id', $ids)->sum('precio_base');
            if ($extra > 0) {
                $venta->increment('importe_total', $extra);
            }
        }

        // 3) limpiar el repeater virtual y refrescar estado entrega
        $this->form->fill(['nuevas_ofertas' => []]);
        $venta->refreshEstadoEntrega();
    }
}
