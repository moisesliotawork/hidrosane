<?php

namespace App\Filament\Repartidor\Resources\EntregaSimpleResource\Pages;

use App\Filament\Repartidor\Resources\EntregaSimpleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEntregaSimples extends ListRecords
{
    protected static string $resource = EntregaSimpleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
