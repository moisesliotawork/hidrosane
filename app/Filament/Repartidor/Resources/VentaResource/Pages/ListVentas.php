<?php

namespace App\Filament\Repartidor\Resources\VentaResource\Pages;

use App\Filament\Repartidor\Resources\VentaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVentas extends ListRecords
{
    protected static string $resource = VentaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
