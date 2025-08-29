<?php

namespace App\Filament\Gerente\Resources\MisNumerosResource\Pages;

use App\Filament\Gerente\Resources\MisNumerosResource;
use Filament\Resources\Pages\ListRecords;
use App\Models\Venta;
use Illuminate\Support\Facades\Log;

class ListMisNumeros extends ListRecords
{
    protected static string $resource = MisNumerosResource::class;

    protected function getHeaderActions(): array
    {
        return []; // sin crear/acciones
    }

    public function mount(): void
    {
        parent::mount();

        // Recalcular “por si acaso” lo mismo que se mostrará en la tabla
        $this->recalcularVentasDelRepartidor();
    }

    /**
     * Recalcula importes, comisiones, VTA REP/ESP/AC, PAS C/R y estado de entrega
     * para las ventas del repartidor autenticado que se mostrarán en el resource.
     */
    protected function recalcularVentasDelRepartidor(): void
    {
        $userId = auth()->id();
        if (!$userId) {
            return;
        }

        // Mismo filtro que el resource: ventas del repartidor autenticado
        Venta::query()
            ->where('repartidor_id', $userId)
            // Cargamos lo necesario para evitar N+1
            ->with(['ventaOfertas.oferta', 'ventaOfertas.productos', 'reparto'])
            // Por memoria/tiempo, procesamos en bloques
            ->select(['id', 'repartidor_id', 'num_cuotas']) // columnas mínimas + relaciones eager
            ->chunkById(200, function ($ventas) {
                /** @var \App\Models\Venta $venta */
                foreach ($ventas as $venta) {
                    try {
                        // 1) Importes y cuota mensual
                        $venta->recomputarImportesDesdeOfertas();

                        // 2) Comisiones (entrega total + venta del repartidor)
                        $venta->calcularComisiones(true);

                        // 3) VTA REP / VTA ESP y su acumulado
                        $venta->recomputarVtasRepYEsp(true)
                            ->recalcularVtasAcumuladas(true);

                        // 3b) PAS C / PAS R
                        $venta->calcularPas(true);

                        // 4) Estado de entrega en el reparto asociado
                        $venta->refreshEstadoEntrega();
                    } catch (\Throwable $e) {
                        // No rompas la vista si una venta falla; deja registro
                        Log::warning('Recalculo MisNumeros falló para venta ' . $venta->id . ': ' . $e->getMessage());
                    }
                }
            });
    }
}
