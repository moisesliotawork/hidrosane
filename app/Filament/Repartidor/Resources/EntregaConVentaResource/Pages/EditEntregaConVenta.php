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
}