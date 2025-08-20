<?php

namespace App\Filament\Repartidor\Resources\HistoricoRepartosResource\Pages;

use App\Filament\Repartidor\Resources\HistoricoRepartosResource;
use Filament\Resources\Pages\ListRecords;

class ListHistoricoRepartos extends ListRecords
{
    protected static string $resource = HistoricoRepartosResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
