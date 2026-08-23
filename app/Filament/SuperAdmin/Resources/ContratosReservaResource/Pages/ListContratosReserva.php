<?php

namespace App\Filament\SuperAdmin\Resources\ContratosReservaResource\Pages;

use App\Filament\SuperAdmin\Resources\ContratosReservaResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListContratosReserva extends ListRecords
{
    protected static string $resource = ContratosReservaResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
