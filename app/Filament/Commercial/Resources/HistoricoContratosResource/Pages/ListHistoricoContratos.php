<?php

namespace App\Filament\Commercial\Resources\HistoricoContratosResource\Pages;

use App\Filament\Commercial\Resources\HistoricoContratosResource;
use Filament\Resources\Pages\ListRecords;

class ListHistoricoContratos extends ListRecords
{
    protected static string $resource = HistoricoContratosResource::class;
    protected function getHeaderActions(): array
    {
        return [];
    }
}
