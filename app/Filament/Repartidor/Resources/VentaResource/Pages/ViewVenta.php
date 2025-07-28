<?php

namespace App\Filament\Repartidor\Resources\VentaResource\Pages;

use App\Filament\Repartidor\Resources\VentaResource;
use Filament\Resources\Pages\ViewRecord;

class ViewVenta extends ViewRecord
{
    protected static string $resource = VentaResource::class;

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
