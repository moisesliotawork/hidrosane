<?php

namespace App\Filament\Repartidor\Resources\VentaResource\Pages;

use App\Filament\Repartidor\Resources\VentaResource;
use Filament\Resources\Pages\ViewRecord;

class ViewVenta extends ViewRecord
{
    protected static string $resource = VentaResource::class;

    public bool $showNuloReparto = false;
    public bool $showNuloFinanciero = false;

    public bool $showNuloAusente = false;

    public bool $showEntregaSimple = false;

    protected function getHeaderActions(): array
    {
        return []; // sin botones
    }

    public function getTitle(): string
    {
        $nro = $this->record->nro_contrato;
        return "GESTIONAR Entrega del Contrato {$nro}";
    }
}
