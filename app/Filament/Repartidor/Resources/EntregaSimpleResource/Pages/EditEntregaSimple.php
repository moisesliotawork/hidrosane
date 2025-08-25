<?php

namespace App\Filament\Repartidor\Resources\EntregaSimpleResource\Pages;

use App\Filament\Repartidor\Resources\EntregaSimpleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEntregaSimple extends EditRecord
{
    protected static string $resource = EntregaSimpleResource::class;

    public function getTitle(): string
    {
        return 'DATOS DEL CONTRATO ' . $this->record->nro_contrato;
    }

    protected function afterSave(): void
    {
        /** @var \App\Models\Venta $venta */
        $venta = $this->record->fresh(['ventaOfertas.oferta', 'ventaOfertas.productos']);

        // 1) Recalcular importes y cuota mensual desde las ofertas
        $venta->recomputarImportesDesdeOfertas();

        // 2) Comisiones: entrega (todas las ofertas) + venta (solo líneas vendidas por repartidor)
        $venta->calcularComisiones(true);

        // 3) Ventas hechas por el repartidor: VTA REP (no excepc.) y VTA ESP (excepc.)
        //    y su acumulado VTA AC
        $venta->recomputarVtasRepYEsp(true)
            ->recalcularVtasAcumuladas(true);

        // 4) Estado de entrega (No entregado / Parcial / Completo) en el reparto asociado
        $venta->refreshEstadoEntrega();
    }

}
