<?php

namespace App\Filament\SuperAdmin\Resources\ContratosBorradosResource\Pages;

use App\Filament\SuperAdmin\Resources\ContratosBorradosResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListContratosBorrados extends ListRecords
{
    protected static string $resource = ContratosBorradosResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
