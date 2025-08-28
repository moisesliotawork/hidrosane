<?php

namespace App\Filament\Gerente\Resources\VentaResource\Pages;

use App\Filament\Gerente\Resources\VentaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVentas extends ListRecords
{
    protected static string $resource = VentaResource::class;

    protected function getHeaderActions(): array
    {
        return [
        
        ];
    }
}
