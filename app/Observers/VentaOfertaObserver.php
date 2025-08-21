<?php

namespace App\Observers;

use App\Models\VentaOferta;
use App\Enums\VendidoPor;
use Illuminate\Support\Facades\DB;

class VentaOfertaObserver
{
    /**
     * Al eliminar una VentaOferta:
     * - Si tiene AL MENOS un producto vendido por COMERCIAL → resta de importe_comercial
     * - En otro caso, si tiene AL MENOS un producto vendido por REPARTIDOR → resta de importe_repartidor
     * - Si no hay productos (edge case) → se considera comercial
     * Luego recalcula importe_total y cuota_mensual.
     */
    public function deleting(VentaOferta $vo): void
    {
        // Cargamos lo necesario, evitando N+1
        $vo->loadMissing(['venta', 'oferta', 'productos']);

        $venta = $vo->venta;
        $oferta = $vo->oferta;

        if (!$venta || !$oferta) {
            return; // nada que hacer si falta la venta u oferta
        }

        $precioBase = (float) ($oferta->precio_base ?? 0);

        // ¿Quién la "vendió"?
        // Regla pedida: PRIORIDAD a Comercial si hay al menos una línea de Comercial.
        $tieneComercial = $vo->productos->contains(fn($p) => $p->vendido_por === VendidoPor::Comercial);
        $tieneRepartidor = $vo->productos->contains(fn($p) => $p->vendido_por === VendidoPor::Repartidor);

        $campo = 'importe_comercial'; // default si no hay productos
        if (!$tieneComercial && $tieneRepartidor) {
            $campo = 'importe_repartidor';
        }

        DB::transaction(function () use ($venta, $campo, $precioBase) {
            // Restar sin permitir negativos
            $venta->{$campo} = max(0, (float) $venta->{$campo} - $precioBase);

            // Recalcular total y cuota
            $venta->importe_total = (float) $venta->importe_comercial + (float) $venta->importe_repartidor;

            if ((int) $venta->num_cuotas > 0) {
                $venta->cuota_mensual = round($venta->importe_total / (int) $venta->num_cuotas, 2);
            }

            $venta->save();
        });
    }
}
