<?php

namespace App\Filament\Repartidor\Resources\EntregaConVentaResource\Pages;

use App\Filament\Repartidor\Resources\EntregaConVentaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Repartidor\Pages\MisRepartos;

class ListEntregaConVentas extends ListRecords
{
    protected static string $resource = EntregaConVentaResource::class;

    public function mount(): void
    {
        // Al intentar entrar al index del resource, redirige a Mis Repartos
        $this->redirect(MisRepartos::getUrl());
    }

    protected function getHeaderActions(): array
    {
        return [
            
        ];
    }
}
