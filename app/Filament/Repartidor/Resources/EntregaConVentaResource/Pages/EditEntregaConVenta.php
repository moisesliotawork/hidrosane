<?php

namespace App\Filament\Repartidor\Resources\EntregaConVentaResource\Pages;

use App\Filament\Repartidor\Resources\EntregaConVentaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEntregaConVenta extends EditRecord
{
    protected static string $resource = EntregaConVentaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
