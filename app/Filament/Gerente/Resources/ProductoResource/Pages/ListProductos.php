<?php

namespace App\Filament\Gerente\Resources\ProductoResource\Pages;

use App\Filament\Gerente\Resources\ProductoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductos extends ListRecords
{
    protected static string $resource = ProductoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
