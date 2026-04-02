<?php

namespace App\Filament\SuperAdmin\Resources\VentaResource\Pages;

use App\Filament\SuperAdmin\Resources\VentaResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListVentas extends ListRecords
{
    protected static string $resource = VentaResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
