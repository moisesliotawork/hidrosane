<?php

namespace App\Filament\Admin\Resources\HojaRutaResource\Pages;

use App\Filament\Admin\Resources\HojaRutaResource;
use Filament\Resources\Pages\ListRecords;

class ListHojaRuta extends ListRecords
{
    protected static string $resource = HojaRutaResource::class;

    // Quita el botón “Create”
    protected function getHeaderActions(): array
    {
        return [];
    }
}
