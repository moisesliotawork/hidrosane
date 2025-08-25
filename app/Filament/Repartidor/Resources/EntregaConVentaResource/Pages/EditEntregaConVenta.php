<?php

namespace App\Filament\Repartidor\Resources\EntregaConVentaResource\Pages;

use App\Filament\Repartidor\Resources\EntregaConVentaResource;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Repartidor\Pages\MisRepartos;

class EditEntregaConVenta extends EditRecord
{
    protected static string $resource = EntregaConVentaResource::class;

    public function getTitle(): string
    {
        return 'Entrega con VENTA';
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Entregar')
                ->color('success'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return MisRepartos::getUrl();
    }

    // 1) Guardar el valor inicial de cuotas
    protected ?int $oldNumCuotas = null; // ← NUEVO

    public function mount($record): void // ← NUEVO
    {
        parent::mount($record);
        $this->oldNumCuotas = (int) ($this->record->num_cuotas ?? 0);
    }

    protected function afterSave(): void
    {
        /** @var \App\Models\Venta $venta */
        $venta = $this->record->fresh(['ventaOfertas.oferta', 'ventaOfertas.productos']);

        // 2) Bono por CON PAGÓ si baja el nº de cuotas
        if (
            $this->oldNumCuotas !== null
            && (int) $venta->num_cuotas < (int) $this->oldNumCuotas
        ) {
            $venta->com_conpago = 50.0;
            $venta->save();
        }

        // 1) Recalcular importes y cuota mensual desde las ofertas
        $venta->recomputarImportesDesdeOfertas();

        // 2) Comisiones: entrega (todas las ofertas) + venta (solo líneas vendidas por repartidor)
        $venta->calcularComisiones(true);

        // 3) Ventas hechas por el repartidor: VTA REP (no excepc.) y VTA ESP (excepc.)
        //    y su acumulado VTA AC
        $venta->recomputarVtasRepYEsp(true)
            ->recalcularVtasAcumuladas(true);

        $venta->calcularPas(true);

        // 4) Estado de entrega (No entregado / Parcial / Completo) en el reparto asociado
        $venta->refreshEstadoEntrega();
    }
}